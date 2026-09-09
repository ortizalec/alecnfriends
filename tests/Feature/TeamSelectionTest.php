<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\LeagueSetting;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from the team page', function () {
    $this->get(route('team.edit'))->assertRedirect(route('login'));
});

test('authenticated users can view the available cast members', function () {
    $user = User::factory()->create();
    $castMember = CastMember::factory()->create(['name' => 'Avery Stone']);

    $this->actingAs($user)
        ->get(route('team.edit'))
        ->assertSee('Choose your team')
        ->assertSee($castMember->name);
});

test('a user can save exactly five active cast members', function () {
    $user = User::factory()->create();
    $castMembers = CastMember::factory()->count(5)->create();

    $this->actingAs($user);

    Livewire::test('pages::team')
        ->set('selectedCastMemberIds', $castMembers->modelKeys())
        ->call('save')
        ->assertHasNoErrors();

    expect($user->castMembers()->pluck('cast_members.id')->all())
        ->toEqualCanonicalizing($castMembers->modelKeys());
});

test('a team must contain exactly five cast members', function (array $castMemberIndexes) {
    $user = User::factory()->create();
    $castMembers = CastMember::factory()->count(6)->create();

    $this->actingAs($user);

    Livewire::test('pages::team')
        ->set('selectedCastMemberIds', collect($castMemberIndexes)->map(fn (int $index) => $castMembers[$index]->id)->all())
        ->call('save')
        ->assertHasErrors(['selectedCastMemberIds']);

    expect($user->castMembers()->count())->toBe(0);
})->with([
    'four choices' => [[0, 1, 2, 3]],
    'six choices' => [[0, 1, 2, 3, 4, 5]],
]);

test('inactive cast members cannot be selected', function () {
    $user = User::factory()->create();
    $activeCastMembers = CastMember::factory()->count(4)->create();
    $inactiveCastMember = CastMember::factory()->create([
        'is_active' => false,
        'status' => CastMemberStatus::Murdered,
    ]);

    $this->actingAs($user);

    Livewire::test('pages::team')
        ->set('selectedCastMemberIds', [...$activeCastMembers->modelKeys(), $inactiveCastMember->id])
        ->call('save')
        ->assertHasErrors(['selectedCastMemberIds.4']);

    expect($user->castMembers()->count())->toBe(0);
});

test('the same cast member cannot fill multiple team spots', function () {
    $user = User::factory()->create();
    $castMember = CastMember::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::team')
        ->set('selectedCastMemberIds', array_fill(0, 5, $castMember->id))
        ->call('save')
        ->assertHasErrors(['selectedCastMemberIds.1']);

    expect($user->castMembers()->count())->toBe(0);
});

test('a saved team cannot be changed after team selection closes', function () {
    $user = User::factory()->create();
    $originalTeam = CastMember::factory()->count(5)->create();
    $replacementTeam = CastMember::factory()->count(5)->create();
    $user->castMembers()->attach($originalTeam);
    LeagueSetting::factory()->create(['team_selection_open' => false]);

    $this->actingAs($user);

    Livewire::test('pages::team')
        ->set('selectedCastMemberIds', $replacementTeam->modelKeys())
        ->call('save')
        ->assertForbidden();

    expect($user->castMembers()->pluck('cast_members.id')->all())
        ->toEqualCanonicalizing($originalTeam->modelKeys());
});

test('team selection controls are hidden after selection closes', function () {
    $user = User::factory()->create();
    $user->castMembers()->attach(CastMember::factory()->count(5)->create());
    LeagueSetting::factory()->create(['team_selection_open' => false]);

    $this->actingAs($user)->get(route('team.edit'))
        ->assertSeeText('My team')
        ->assertDontSeeText('of 5 selected')
        ->assertDontSeeText('Save team');
});

test('my team activity includes only actions for selected cast members', function () {
    $user = User::factory()->create();
    $teamMember = CastMember::factory()->create(['name' => 'Team Player']);
    $otherMember = CastMember::factory()->create(['name' => 'Other Player']);
    $user->castMembers()->attach($teamMember);
    LeagueSetting::factory()->create(['team_selection_open' => false]);
    CastMemberAction::factory()->for($teamMember)->create();
    CastMemberAction::factory()->for($otherMember)->create();

    $this->actingAs($user)->get(route('team.edit'))
        ->assertSeeText('Team Player')
        ->assertDontSeeText('Other Player');
});
