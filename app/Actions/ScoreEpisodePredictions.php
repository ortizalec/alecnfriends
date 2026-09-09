<?php

namespace App\Actions;

use App\Models\Episode;

class ScoreEpisodePredictions
{
    public function __invoke(Episode $episode): void
    {
        $episode->predictions()->each(function ($prediction) use ($episode): void {
            $points = 0;
            $points += $episode->murdered_cast_member_id !== null && $prediction->murdered_cast_member_id === $episode->murdered_cast_member_id ? 1 : 0;
            $points += $episode->banished_cast_member_id !== null && $prediction->banished_cast_member_id === $episode->banished_cast_member_id ? 1 : 0;
            $points += $episode->breakfast_cast_member_id !== null && $prediction->breakfast_cast_member_id === $episode->breakfast_cast_member_id ? 2 : 0;

            $prediction->update(['points' => $points]);
        });
    }
}
