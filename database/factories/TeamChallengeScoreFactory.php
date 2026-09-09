<?php

namespace Database\Factories;

use App\Models\Episode;
use App\Models\TeamChallengeScore;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamChallengeScore>
 */
class TeamChallengeScoreFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'episode_id' => Episode::factory(),
            'user_id' => User::factory(),
            'points' => 1,
        ];
    }
}
