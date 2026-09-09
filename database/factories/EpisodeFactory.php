<?php

namespace Database\Factories;

use App\Models\Episode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Episode>
 */
class EpisodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numberBetween(1, 100),
            'title' => fake()->sentence(3),
            'airs_at' => now()->addWeek(),
            'predictions_close_at' => now()->addDays(6),
            'predictions_are_open' => false,
        ];
    }
}
