<?php

namespace Database\Factories;

use App\Models\CastMember;
use App\Models\Episode;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prediction>
 */
class PredictionFactory extends Factory
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
            'murdered_cast_member_id' => CastMember::factory(),
            'banished_cast_member_id' => CastMember::factory(),
            'breakfast_cast_member_id' => CastMember::factory(),
        ];
    }
}
