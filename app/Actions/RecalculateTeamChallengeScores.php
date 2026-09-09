<?php

namespace App\Actions;

use App\CastMemberActionType;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\TeamChallengeScore;
use App\Models\User;

class RecalculateTeamChallengeScores
{
    public function __invoke(Episode $episode): void
    {
        TeamChallengeScore::query()->whereBelongsTo($episode)->delete();

        $earningCastMemberIds = CastMemberAction::query()
            ->whereBelongsTo($episode)
            ->where('type', CastMemberActionType::ChallengeMoney)
            ->distinct()->pluck('cast_member_id');

        if ($earningCastMemberIds->isEmpty()) {
            return;
        }

        User::query()->where('is_admin', false)
            ->whereHas('castMembers', fn ($query) => $query->whereIn('cast_members.id', $earningCastMemberIds))
            ->each(fn (User $user) => TeamChallengeScore::query()->create([
                'episode_id' => $episode->id,
                'user_id' => $user->id,
                'points' => 1,
            ]));
    }
}
