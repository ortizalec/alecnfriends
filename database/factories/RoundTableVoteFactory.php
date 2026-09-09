<?php

namespace Database\Factories;

use App\Models\CastMember;
use App\Models\Episode;
use App\Models\RoundTableVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoundTableVote>
 */
class RoundTableVoteFactory extends Factory
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
            'voter_cast_member_id' => CastMember::factory(),
            'target_cast_member_id' => CastMember::factory(),
            'created_by' => User::factory()->admin(),
        ];
    }
}
