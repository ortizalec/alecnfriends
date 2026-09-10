<?php

use App\Models\CastMember;
use App\Models\Episode;
use App\Models\LeagueSetting;
use App\Models\Poll;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('players cannot access the admin dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins can access the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertSee('League administration');
});

test('admins can add a cast member', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('name', 'Jordan Rivers')
        ->set('status', 'active')
        ->set('points', 4)
        ->call('saveCastMember')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('cast_members', [
        'name' => 'Jordan Rivers',
        'is_active' => true,
        'status' => 'active',
        'points' => 4,
    ]);
});

test('admins can upload a cast member photo to public storage', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('name', 'Jordan Rivers')
        ->set('photo', UploadedFile::fake()->image('jordan.jpg'))
        ->set('status', 'active')
        ->call('saveCastMember')
        ->assertHasNoErrors();

    $castMember = CastMember::query()->where('name', 'Jordan Rivers')->firstOrFail();

    expect($castMember->photo_path)->toStartWith('cast-members/');
    Storage::disk('public')->assertExists($castMember->photo_path);
});

test('cast member uploads must be safe image files', function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('name', 'Jordan Rivers')
        ->set('photo', UploadedFile::fake()->create('cast.svg', 20, 'image/svg+xml'))
        ->call('saveCastMember')
        ->assertHasErrors(['photo']);

    $this->assertDatabaseMissing('cast_members', ['name' => 'Jordan Rivers']);
});

test('players cannot call admin actions', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('name', 'Unauthorized Cast Member')
        ->call('saveCastMember')
        ->assertForbidden();

    $this->assertDatabaseMissing('cast_members', ['name' => 'Unauthorized Cast Member']);
});

test('admins can close team selection', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->call('toggleTeamSelection')
        ->assertHasNoErrors();

    expect(LeagueSetting::teamSelectionIsOpen())->toBeFalse();
});

test('admins can label a cast member as murdered', function () {
    $admin = User::factory()->admin()->create();
    $castMember = CastMember::factory()->create();
    $this->actingAs($admin);

    Livewire::test('pages::admin.dashboard')
        ->call('setCastMemberStatus', $castMember->id, 'murdered')
        ->assertHasNoErrors();

    expect($castMember->refresh()->status->value)->toBe('murdered')
        ->and($castMember->is_active)->toBeFalse();
});

test('admins can label a cast member as a traitor', function () {
    $admin = User::factory()->admin()->create();
    $castMember = CastMember::factory()->create(['is_traitor' => false]);
    $this->actingAs($admin);

    Livewire::test('pages::admin.dashboard')
        ->call('editCastMember', $castMember->id)
        ->set('isTraitor', true)
        ->call('saveCastMember')
        ->assertHasNoErrors();

    expect($castMember->refresh()->is_traitor)->toBeTrue();
});

test('admins can create a published poll', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('pollQuestion', 'Who played the best game?')
        ->set('pollMaximumSelections', 2)
        ->set('publishPoll', true)
        ->call('savePoll')
        ->assertHasNoErrors();

    $poll = Poll::query()->firstOrFail();
    expect($poll->question)->toBe('Who played the best game?')
        ->and($poll->maximum_selections)->toBe(2)
        ->and($poll->is_published)->toBeTrue();
});

test('admins can launch episode predictions', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.dashboard')
        ->set('episodeNumber', 3)
        ->set('episodeTitle', 'The Third Round Table')
        ->set('predictionClosesAt', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('launchPredictions')
        ->assertHasNoErrors();

    $episode = Episode::query()->firstOrFail();
    expect($episode->number)->toBe(3)
        ->and($episode->predictions_are_open)->toBeTrue();
});

test('admins can clear a player team', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();
    $player->castMembers()->attach(CastMember::factory()->count(5)->create());

    $this->actingAs($admin);

    Livewire::test('pages::admin.dashboard')
        ->call('clearTeam', $player->id)
        ->assertHasNoErrors();

    expect($player->castMembers()->count())->toBe(0);
});

test('the admin seeder creates a verified administrator', function () {
    config()->set('app.admin', [
        'name' => 'League Admin',
        'email' => 'admin@example.com',
        'password' => 'a-secure-test-password',
    ]);

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

    expect($admin->is_admin)->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check('a-secure-test-password', $admin->password))->toBeTrue();
});
