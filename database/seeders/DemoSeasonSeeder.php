<?php

namespace Database\Seeders;

use App\Actions\RecalculateTeamChallengeScores;
use App\Actions\RecalculateVoteScoring;
use App\Actions\ScoreEpisodePredictions;
use App\CastMemberActionType;
use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\Prediction;
use App\Models\RoundTableVote;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeasonSeeder extends Seeder
{
    public function run(ScoreEpisodePredictions $scorePredictions, RecalculateVoteScoring $recalculateVoteScoring, RecalculateTeamChallengeScores $recalculateTeamChallengeScores): void
    {
        $castMembers = CastMember::query()->oldest('id')->take(22)->get();
        $players = User::query()->where('is_admin', false)->oldest('id')->get();
        $admin = User::query()->where('is_admin', true)->firstOrFail();
        if ($castMembers->count() < 12 || $players->isEmpty()) {
            return;
        }

        CastMember::query()->update(['is_traitor' => false, 'status' => CastMemberStatus::Active, 'is_active' => true]);
        CastMember::query()->whereKey([$castMembers[1]->id, $castMembers[4]->id, $castMembers[10]->id])->update(['is_traitor' => true]);

        foreach (range(1, 5) as $episodeNumber) {
            $murdered = $castMembers[$episodeNumber * 2 + 3];
            $banished = $castMembers[$episodeNumber * 2 + 4];
            $breakfast = $castMembers[$episodeNumber - 1];
            $episode = Episode::query()->updateOrCreate(['number' => $episodeNumber], [
                'title' => 'Demo Episode '.$episodeNumber, 'airs_at' => now()->subWeeks(6 - $episodeNumber),
                'predictions_close_at' => now()->subWeeks(6 - $episodeNumber)->subHour(), 'predictions_are_open' => false,
                'murdered_cast_member_id' => $murdered->id, 'banished_cast_member_id' => $banished->id, 'breakfast_cast_member_id' => $breakfast->id,
            ]);

            foreach ($players as $playerIndex => $player) {
                Prediction::query()->updateOrCreate(['episode_id' => $episode->id, 'user_id' => $player->id], [
                    'murdered_cast_member_id' => $playerIndex % 3 === 0 ? $murdered->id : $castMembers[($episodeNumber + $playerIndex + 5) % 22]->id,
                    'banished_cast_member_id' => $playerIndex % 4 === 0 ? $banished->id : $castMembers[($episodeNumber + $playerIndex + 8) % 22]->id,
                    'breakfast_cast_member_id' => $playerIndex % 2 === 0 ? $breakfast->id : $castMembers[($episodeNumber + $playerIndex + 11) % 22]->id,
                ]);
            }

            $shieldWinner = $castMembers[($episodeNumber + 1) % 22];
            $shieldAction = CastMemberAction::query()->firstOrCreate([
                'episode_id' => $episode->id, 'cast_member_id' => $shieldWinner->id, 'created_by' => $admin->id, 'type' => CastMemberActionType::Shield,
            ], ['points' => 2]);
            if ($shieldAction->wasRecentlyCreated) {
                $shieldWinner->increment('points', 2);
            }
            foreach ([$castMembers[$episodeNumber], $castMembers[$episodeNumber + 1]] as $moneyWinner) {
                CastMemberAction::query()->firstOrCreate([
                    'episode_id' => $episode->id, 'cast_member_id' => $moneyWinner->id, 'created_by' => $admin->id, 'type' => CastMemberActionType::ChallengeMoney,
                ], ['points' => 1]);
            }
            foreach ($castMembers->take(8) as $voterIndex => $voter) {
                $target = $castMembers[($voterIndex + $episodeNumber + 1) % 12];
                RoundTableVote::query()->updateOrCreate(
                    ['episode_id' => $episode->id, 'voter_cast_member_id' => $voter->id],
                    ['target_cast_member_id' => $target->id, 'created_by' => $admin->id],
                );
            }

            $scorePredictions($episode);
            $recalculateVoteScoring($episode);
            $recalculateTeamChallengeScores($episode);
            $murdered->update(['status' => CastMemberStatus::Murdered, 'is_active' => false]);
            $banished->update(['status' => CastMemberStatus::Banished, 'is_active' => false]);
        }

        foreach ([['Who is playing the strongest faithful game?', 2], ['Who is most likely to be recruited as a traitor?', 1], ['Who had the best round-table performance?', 2]] as $surveyIndex => [$question, $maximumSelections]) {
            $poll = Poll::query()->updateOrCreate(['question' => $question], [
                'maximum_selections' => $maximumSelections, 'opens_at' => now()->subDays(5), 'closes_at' => now()->addDays(5 + $surveyIndex), 'is_published' => true,
            ]);
            foreach ($players as $playerIndex => $player) {
                foreach (range(0, $maximumSelections - 1) as $choiceOffset) {
                    PollResponse::query()->firstOrCreate([
                        'poll_id' => $poll->id, 'user_id' => $player->id,
                        'cast_member_id' => $castMembers[($playerIndex + $surveyIndex * 3 + $choiceOffset) % 12]->id,
                    ]);
                }
            }
        }
    }
}
