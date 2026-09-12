<?php

use App\Models\CastMember;
use App\Models\TraitorPrediction;
use App\Models\TraitorPredictionRound;
use App\Models\User;
use Livewire\Livewire;

test('an admin can launch the one-off traitor prediction', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->call('launchTraitorPrediction')
        ->assertHasNoErrors();

    $round = TraitorPredictionRound::query()->firstOrFail();
    expect($round->is_open)->toBeTrue()
        ->and($round->revealed_at)->toBeNull();
});

test('players cannot launch the traitor prediction', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.dashboard')
        ->call('launchTraitorPrediction')
        ->assertForbidden();

    expect(TraitorPredictionRound::query()->count())->toBe(0);
});

test('an open traitor prediction is shown to players', function () {
    $user = User::factory()->create();
    TraitorPredictionRound::factory()->open()->create();
    CastMember::factory()->count(3)->create();

    $this->actingAs($user)
        ->get(route('predictions.edit'))
        ->assertSeeText('Who are the traitors?')
        ->assertSeeText('Save traitor picks');

    $this->get(route('dashboard'))
        ->assertSeeText('Who do you think the traitors are?')
        ->assertSeeText('Pick the traitors');
});

test('a player can save three distinct traitor picks while the round is open', function () {
    $user = User::factory()->create();
    $round = TraitorPredictionRound::factory()->open()->create();
    $castMembers = CastMember::factory()->count(3)->create();

    $this->actingAs($user);

    Livewire::test('pages::predictions')
        ->set('firstTraitorCastMemberId', $castMembers[0]->id)
        ->set('secondTraitorCastMemberId', $castMembers[1]->id)
        ->set('thirdTraitorCastMemberId', $castMembers[2]->id)
        ->call('saveTraitorPrediction')
        ->assertHasNoErrors();

    $prediction = TraitorPrediction::query()->whereBelongsTo($round, 'round')->whereBelongsTo($user)->firstOrFail();
    expect($prediction->first_cast_member_id)->toBe($castMembers[0]->id)
        ->and($prediction->second_cast_member_id)->toBe($castMembers[1]->id)
        ->and($prediction->third_cast_member_id)->toBe($castMembers[2]->id);
});

test('traitor picks must contain three different cast members', function () {
    $user = User::factory()->create();
    TraitorPredictionRound::factory()->open()->create();
    $castMembers = CastMember::factory()->count(2)->create();

    $this->actingAs($user);

    Livewire::test('pages::predictions')
        ->set('firstTraitorCastMemberId', $castMembers[0]->id)
        ->set('secondTraitorCastMemberId', $castMembers[0]->id)
        ->set('thirdTraitorCastMemberId', $castMembers[1]->id)
        ->call('saveTraitorPrediction')
        ->assertHasErrors(['secondTraitorCastMemberId']);

    expect(TraitorPrediction::query()->count())->toBe(0);
});

test('an admin can reveal the correct traitors and score every submission', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $round = TraitorPredictionRound::factory()->open()->create();
    $castMembers = CastMember::factory()->count(4)->create();
    TraitorPrediction::factory()->for($round, 'round')->for($player)->create([
        'first_cast_member_id' => $castMembers[0]->id,
        'second_cast_member_id' => $castMembers[1]->id,
        'third_cast_member_id' => $castMembers[3]->id,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.dashboard')
        ->set('firstCorrectTraitorCastMemberId', $castMembers[0]->id)
        ->set('secondCorrectTraitorCastMemberId', $castMembers[1]->id)
        ->set('thirdCorrectTraitorCastMemberId', $castMembers[2]->id)
        ->call('saveTraitorPredictionResults')
        ->assertHasNoErrors();

    expect($round->refresh()->is_open)->toBeFalse()
        ->and($round->revealed_at)->not->toBeNull()
        ->and($round->predictions()->firstOrFail()->points)->toBe(2)
        ->and(CastMember::query()->where('is_traitor', true)->pluck('id')->all())
        ->toEqualCanonicalizing([$castMembers[0]->id, $castMembers[1]->id, $castMembers[2]->id]);
});

test('players cannot change traitor picks after the answers are revealed', function () {
    $user = User::factory()->create();
    TraitorPredictionRound::factory()->revealed()->create();
    $castMembers = CastMember::factory()->count(3)->create();

    $this->actingAs($user);

    Livewire::test('pages::predictions')
        ->set('firstTraitorCastMemberId', $castMembers[0]->id)
        ->set('secondTraitorCastMemberId', $castMembers[1]->id)
        ->set('thirdTraitorCastMemberId', $castMembers[2]->id)
        ->call('saveTraitorPrediction')
        ->assertForbidden();

    expect(TraitorPrediction::query()->count())->toBe(0);
});

test('traitor prediction points are included in the leaderboard total', function () {
    $player = User::factory()->create(['name' => 'Preseason Leader']);
    $prediction = TraitorPrediction::factory()->for($player)->create(['points' => 3]);

    $this->actingAs($player)
        ->get(route('dashboard'))
        ->assertSeeInOrder(['Preseason Leader', '3']);

    expect($prediction->points)->toBe(3);
});
