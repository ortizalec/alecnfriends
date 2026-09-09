<?php

namespace Database\Factories;

use App\CastMemberActionType;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CastMemberAction>
 */
class CastMemberActionFactory extends Factory
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
            'cast_member_id' => CastMember::factory(),
            'created_by' => User::factory()->admin(),
            'type' => CastMemberActionType::ChallengeMoney,
            'points' => 1,
        ];
    }
}
