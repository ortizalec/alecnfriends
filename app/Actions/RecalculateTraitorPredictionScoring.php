<?php

namespace App\Actions;

use App\Models\TraitorPredictionRound;

class RecalculateTraitorPredictionScoring
{
    public function __invoke(TraitorPredictionRound $round): void
    {
        $correctCastMemberIds = collect([
            $round->first_traitor_cast_member_id,
            $round->second_traitor_cast_member_id,
            $round->third_traitor_cast_member_id,
        ])->filter();

        $round->predictions()->each(function ($prediction) use ($correctCastMemberIds): void {
            $predictedCastMemberIds = collect([
                $prediction->first_cast_member_id,
                $prediction->second_cast_member_id,
                $prediction->third_cast_member_id,
            ]);

            $prediction->update([
                'points' => $predictedCastMemberIds->intersect($correctCastMemberIds)->count(),
            ]);
        });
    }
}
