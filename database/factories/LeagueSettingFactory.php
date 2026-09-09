<?php

namespace Database\Factories;

use App\Models\LeagueSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeagueSetting>
 */
class LeagueSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_selection_open' => true,
        ];
    }
}
