<?php

namespace App\Actions;

use App\CastMemberActionType;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\RoundTableVote;
use Illuminate\Support\Facades\DB;

class RecalculateVoteScoring
{
    public function __invoke(Episode $episode): void
    {
        DB::transaction(function () use ($episode): void {
            $existingActions = CastMemberAction::query()
                ->whereBelongsTo($episode)
                ->whereNotNull('round_table_vote_id')
                ->get();

            foreach ($existingActions->groupBy('cast_member_id') as $castMemberId => $actions) {
                $castMember = CastMember::query()->lockForUpdate()->findOrFail($castMemberId);
                $castMember->update(['points' => max(0, $castMember->points - $actions->sum('points'))]);
            }

            CastMemberAction::query()->whereKey($existingActions->modelKeys())->delete();

            $votes = $episode->roundTableVotes()->with(['voter', 'target'])->get();
            $votesPerTarget = $votes->countBy('target_cast_member_id');

            foreach ($votes as $vote) {
                if (! $vote->voter->is_traitor && $vote->target->is_traitor) {
                    $this->award($vote->voter, $vote, CastMemberActionType::FaithfulVotesTraitor);
                }

                if ($votesPerTarget->get($vote->target_cast_member_id) === 1) {
                    $this->award($vote->voter, $vote, CastMemberActionType::LoneVote);
                }

                if ($vote->target->is_traitor && $episode->banished_cast_member_id !== $vote->target_cast_member_id) {
                    $this->award($vote->target, $vote, CastMemberActionType::TraitorSurvivesVote);
                }
            }
        });
    }

    private function award(CastMember $castMember, RoundTableVote $vote, CastMemberActionType $type): void
    {
        CastMemberAction::query()->create([
            'episode_id' => $vote->episode_id,
            'cast_member_id' => $castMember->id,
            'created_by' => $vote->created_by,
            'round_table_vote_id' => $vote->id,
            'type' => $type,
            'points' => $type->points(),
        ]);
        $castMember->increment('points', $type->points());
    }
}
