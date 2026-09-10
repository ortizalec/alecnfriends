<?php

use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Poll;
use App\Models\Prediction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

test('database seeding creates five complete example episodes and surveys', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Episode::query()->whereNotNull('murdered_cast_member_id')->whereNotNull('banished_cast_member_id')->count())->toBe(5)
        ->and(CastMemberAction::query()->count())->toBeGreaterThan(0)
        ->and(Poll::query()->where('is_published', true)->count())->toBeGreaterThanOrEqual(3)
        ->and(Prediction::query()->count())->toBe(User::query()->where('is_admin', false)->count() * 5)
        ->and(CastMember::query()->where('status', 'murdered')->count())->toBe(5)
        ->and(CastMember::query()->where('status', 'banished')->count())->toBe(5);
});
