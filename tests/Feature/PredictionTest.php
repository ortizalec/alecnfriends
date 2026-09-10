<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\Episode;
use App\Models\Prediction;
use App\Models\User;
use Livewire\Livewire;

test('only active cast members appear in predictions', function () {
    $user = User::factory()->create();
    Episode::factory()->create(['number' => 1, 'predictions_are_open' => true]);
    $active = CastMember::factory()->create(['name' => 'Active Player']);
    CastMember::factory()->create(['name' => 'Murdered Player', 'status' => CastMemberStatus::Murdered, 'is_active' => false]);
    CastMember::factory()->create(['name' => 'Banished Player', 'status' => CastMemberStatus::Banished, 'is_active' => false]);

    $this->actingAs($user)
        ->get(route('predictions.edit'))
        ->assertSee($active->name)
        ->assertDontSee('Murdered Player')
        ->assertDontSee('Banished Player');
});

test('a user can save predictions for an open episode', function () {
    $user = User::factory()->create();
    $episode = Episode::factory()->create(['predictions_are_open' => true, 'predictions_close_at' => now()->addDay()]);
    $castMembers = CastMember::factory()->count(3)->create();

    $this->actingAs($user);

    Livewire::test('pages::predictions')
        ->set('murderedCastMemberId', $castMembers[0]->id)
        ->set('banishedCastMemberId', $castMembers[1]->id)
        ->set('breakfastCastMemberId', $castMembers[2]->id)
        ->call('save')
        ->assertHasNoErrors();

    $prediction = Prediction::query()->whereBelongsTo($episode)->whereBelongsTo($user)->firstOrFail();
    expect($prediction->murdered_cast_member_id)->toBe($castMembers[0]->id)
        ->and($prediction->banished_cast_member_id)->toBe($castMembers[1]->id)
        ->and($prediction->breakfast_cast_member_id)->toBe($castMembers[2]->id);
});

test('an eliminated cast member cannot be submitted as a prediction', function () {
    $user = User::factory()->create();
    Episode::factory()->create(['predictions_are_open' => true, 'predictions_close_at' => now()->addDay()]);
    $active = CastMember::factory()->count(2)->create();
    $murdered = CastMember::factory()->create(['status' => CastMemberStatus::Murdered, 'is_active' => false]);

    $this->actingAs($user);

    Livewire::test('pages::predictions')
        ->set('murderedCastMemberId', $murdered->id)
        ->set('banishedCastMemberId', $active[0]->id)
        ->set('breakfastCastMemberId', $active[1]->id)
        ->call('save')
        ->assertHasErrors(['murderedCastMemberId']);

    expect(Prediction::query()->count())->toBe(0);
});

test('users can review their prediction history with correctness and points', function () {
    $user = User::factory()->create();
    $castMembers = CastMember::factory()->count(4)->create();
    $episode = Episode::factory()->create([
        'number' => 4,
        'title' => 'The Fourth Round Table',
        'murdered_cast_member_id' => $castMembers[0]->id,
        'banished_cast_member_id' => $castMembers[1]->id,
        'breakfast_cast_member_id' => $castMembers[2]->id,
    ]);
    Prediction::factory()->for($episode)->for($user)->create([
        'murdered_cast_member_id' => $castMembers[0]->id,
        'banished_cast_member_id' => $castMembers[3]->id,
        'breakfast_cast_member_id' => $castMembers[2]->id,
        'points' => 3,
    ]);

    $this->actingAs($user)->get(route('predictions.edit'))
        ->assertSeeText('Prediction history')
        ->assertSeeText('Episode 4')
        ->assertSeeText('3 points')
        ->assertSeeText('Correct +1')
        ->assertSeeText('Correct +2')
        ->assertSeeText('Incorrect');
});
