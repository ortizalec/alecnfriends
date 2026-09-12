<?php

use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Prediction;
use App\Models\TeamChallengeScore;
use App\Models\User;
use Livewire\Livewire;

test('players cannot access live scoring', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.scoring'))
        ->assertForbidden();
});

test('players cannot record scoring actions directly', function () {
    $episode = Episode::factory()->create();
    $castMember = CastMember::factory()->create(['points' => 0]);
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('castMemberId', $castMember->id)
        ->set('actionType', 'shield')
        ->call('recordAction')
        ->assertForbidden();

    expect(CastMemberAction::query()->count())->toBe(0)
        ->and($castMember->refresh()->points)->toBe(0);
});

test('recording and removing a shield updates cast points', function () {
    $admin = User::factory()->admin()->create();
    $episode = Episode::factory()->create();
    $castMember = CastMember::factory()->create(['points' => 3]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('castMemberId', $castMember->id)
        ->set('actionType', 'shield')
        ->call('recordAction')
        ->assertHasNoErrors();

    $action = CastMemberAction::query()->firstOrFail();
    expect($action->points)->toBe(2)
        ->and($castMember->refresh()->points)->toBe(5);

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->call('deleteAction', $action->id)
        ->assertHasNoErrors();

    expect($action->fresh())->toBeNull()
        ->and($castMember->refresh()->points)->toBe(3);
});

test('challenge money awards one point per fantasy team instead of per cast member', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $episode = Episode::factory()->create();
    $castMembers = CastMember::factory()->count(2)->create(['points' => 0]);
    $player->castMembers()->attach($castMembers);
    $this->actingAs($admin);

    foreach ($castMembers as $castMember) {
        Livewire::test('pages::admin.scoring')
            ->set('episodeId', $episode->id)
            ->set('castMemberId', $castMember->id)
            ->set('actionType', 'challenge_money')
            ->call('recordAction')
            ->assertHasNoErrors();
    }

    expect($castMembers[0]->refresh()->points)->toBe(0)
        ->and($castMembers[1]->refresh()->points)->toBe(0)
        ->and(TeamChallengeScore::query()->whereBelongsTo($player)->sum('points'))->toBe(1);
});

test('official results can be saved separately as they happen', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $episode = Episode::factory()->create(['predictions_are_open' => true]);
    $castMembers = CastMember::factory()->count(4)->create();
    $prediction = Prediction::factory()->create([
        'episode_id' => $episode->id,
        'user_id' => $user->id,
        'murdered_cast_member_id' => $castMembers[0]->id,
        'banished_cast_member_id' => $castMembers[1]->id,
        'breakfast_cast_member_id' => $castMembers[2]->id,
    ]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('murderedCastMemberId', $castMembers[0]->id)
        ->call('saveMurderedResult')
        ->assertHasNoErrors();

    expect($prediction->refresh()->points)->toBe(1)
        ->and($episode->refresh()->predictions_are_open)->toBeFalse()
        ->and($castMembers[0]->refresh()->status->value)->toBe('murdered')
        ->and($episode->banished_cast_member_id)->toBeNull()
        ->and($episode->breakfast_cast_member_id)->toBeNull();

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('banishedCastMemberId', $castMembers[1]->id)
        ->call('saveBanishedResult')
        ->assertHasNoErrors();

    expect($prediction->refresh()->points)->toBe(2)
        ->and($castMembers[1]->refresh()->status->value)->toBe('banished')
        ->and($episode->refresh()->breakfast_cast_member_id)->toBeNull();

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('breakfastCastMemberId', $castMembers[2]->id)
        ->call('saveBreakfastResult')
        ->assertHasNoErrors();

    expect($prediction->refresh()->points)->toBe(4);
});

test('changing official results recalculates prediction points', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $castMembers = CastMember::factory()->count(4)->create();
    $episode = Episode::factory()->create([
        'murdered_cast_member_id' => $castMembers[0]->id,
        'banished_cast_member_id' => $castMembers[1]->id,
        'breakfast_cast_member_id' => $castMembers[2]->id,
    ]);
    $prediction = Prediction::factory()->create([
        'episode_id' => $episode->id,
        'user_id' => $user->id,
        'murdered_cast_member_id' => $castMembers[0]->id,
        'banished_cast_member_id' => $castMembers[1]->id,
        'breakfast_cast_member_id' => $castMembers[2]->id,
        'points' => 4,
    ]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('breakfastCastMemberId', $castMembers[3]->id)
        ->call('saveBreakfastResult')
        ->assertHasNoErrors();

    expect($prediction->refresh()->points)->toBe(2)
        ->and($episode->refresh()->murdered_cast_member_id)->toBe($castMembers[0]->id)
        ->and($episode->banished_cast_member_id)->toBe($castMembers[1]->id)
        ->and($episode->breakfast_cast_member_id)->toBe($castMembers[3]->id);
});

test('live scoring renders a separate form for each official answer', function () {
    $episode = Episode::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.scoring'))
        ->assertSee('wire:submit="saveMurderedResult"', false)
        ->assertSee('wire:submit="saveBanishedResult"', false)
        ->assertSee('wire:submit="saveBreakfastResult"', false);
});
