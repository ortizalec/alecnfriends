<?php

namespace Database\Factories;

use App\Models\CastMember;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollResponse>
 */
class PollResponseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_id' => Poll::factory(),
            'user_id' => User::factory(),
            'cast_member_id' => CastMember::factory(),
        ];
    }
}
