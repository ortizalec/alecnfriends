<?php

namespace Database\Factories;

use App\CastMemberStatus;
use App\Models\CastMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CastMember>
 */
class CastMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->name(),
            'photo_url' => null,
            'is_active' => true,
            'status' => CastMemberStatus::Active,
            'is_traitor' => false,
            'points' => fake()->numberBetween(0, 15),
        ];
    }
}
