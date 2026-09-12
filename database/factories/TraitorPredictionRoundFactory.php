<?php

namespace Database\Factories;

use App\Models\CastMember;
use App\Models\TraitorPredictionRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraitorPredictionRound>
 */
class TraitorPredictionRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_open' => false,
            'first_traitor_cast_member_id' => null,
            'second_traitor_cast_member_id' => null,
            'third_traitor_cast_member_id' => null,
            'revealed_at' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => true,
        ]);
    }

    public function revealed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_open' => false,
            'first_traitor_cast_member_id' => CastMember::factory(),
            'second_traitor_cast_member_id' => CastMember::factory(),
            'third_traitor_cast_member_id' => CastMember::factory(),
            'revealed_at' => now(),
        ]);
    }
}
