<?php

use App\CastMemberActionType;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected from cast member profiles', function () {
    $castMember = CastMember::factory()->create();

    $this->get(route('cast-members.show', $castMember))->assertRedirect(route('login'));
});

test('players see a cast member biography and actions without editing controls', function () {
    $castMember = CastMember::factory()->create(['name' => 'Avery Stone', 'bio' => 'A strategic player with a sharp eye for alliances.']);
    $episode = Episode::factory()->create(['number' => 3]);
    CastMemberAction::factory()->create([
        'cast_member_id' => $castMember->id,
        'episode_id' => $episode->id,
        'type' => CastMemberActionType::Shield,
        'points' => 2,
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cast-members.show', $castMember))
        ->assertSeeText('Avery Stone')
        ->assertSeeText('A strategic player with a sharp eye for alliances.')
        ->assertSeeText('Earned a shield')
        ->assertSeeText('Episode')
        ->assertSeeText('3')
        ->assertSee('logo.png', false)
        ->assertDontSeeText('Save cast member');
});

test('admins can update all cast member profile details', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());
    $castMember = CastMember::factory()->create(['name' => 'Old Name', 'bio' => null]);

    Livewire::test('pages::cast-members.show', ['castMember' => $castMember])
        ->set('name', 'Updated Name')
        ->set('photo', UploadedFile::fake()->image('updated.png'))
        ->set('bio', 'A thoughtful and persuasive competitor.')
        ->set('status', 'banished')
        ->set('isTraitor', true)
        ->set('points', 9)
        ->call('save')
        ->assertHasNoErrors();

    $castMember->refresh();

    expect($castMember->name)->toBe('Updated Name')
        ->and($castMember->bio)->toBe('A thoughtful and persuasive competitor.')
        ->and($castMember->status->value)->toBe('banished')
        ->and($castMember->is_active)->toBeFalse()
        ->and($castMember->is_traitor)->toBeTrue()
        ->and($castMember->points)->toBe(9)
        ->and($castMember->photo_path)->toStartWith('cast-members/');
    Storage::disk('public')->assertExists($castMember->photo_path);
});

test('players cannot update a cast member biography', function () {
    $this->actingAs(User::factory()->create());
    $castMember = CastMember::factory()->create(['bio' => null]);

    Livewire::test('pages::cast-members.show', ['castMember' => $castMember])
        ->set('bio', 'Unauthorized biography.')
        ->call('save')
        ->assertForbidden();

    expect($castMember->refresh()->bio)->toBeNull();
});

test('cast member biographies are escaped', function () {
    $castMember = CastMember::factory()->create(['bio' => '<script>alert("profile")</script>']);

    $response = $this->actingAs(User::factory()->create())
        ->get(route('cast-members.show', $castMember));

    $response->assertSee('&lt;script&gt;', false);
    expect($response->getContent())->not->toContain('<script>alert("profile")</script>');
});
