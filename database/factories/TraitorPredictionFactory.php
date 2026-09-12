<?php

namespace Database\Factories;

use App\Models\CastMember;
use App\Models\TraitorPrediction;
use App\Models\TraitorPredictionRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraitorPrediction>
 */
class TraitorPredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'traitor_prediction_round_id' => TraitorPredictionRound::factory(),
            'user_id' => User::factory(),
            'first_cast_member_id' => CastMember::factory(),
            'second_cast_member_id' => CastMember::factory(),
            'third_cast_member_id' => CastMember::factory(),
            'points' => 0,
        ];
    }
}
