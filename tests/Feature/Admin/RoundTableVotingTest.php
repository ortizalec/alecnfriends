<?php

use App\CastMemberActionType;
use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\RoundTableVote;
use App\Models\User;
use Livewire\Livewire;

test('players cannot record round table votes', function () {
    $episode = Episode::factory()->create();
    $castMembers = CastMember::factory()->count(2)->create();
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('voterCastMemberId', $castMembers[0]->id)
        ->set('voteTargetCastMemberId', $castMembers[1]->id)
        ->call('saveVote')
        ->assertForbidden();

    expect(RoundTableVote::query()->count())->toBe(0);
});

test('a recorded vote automatically awards all applicable vote points', function () {
    $episode = Episode::factory()->create();
    $faithful = CastMember::factory()->create(['points' => 0, 'is_traitor' => false]);
    $traitor = CastMember::factory()->create(['points' => 0, 'is_traitor' => true]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('voterCastMemberId', $faithful->id)
        ->set('voteTargetCastMemberId', $traitor->id)
        ->call('saveVote')
        ->assertHasNoErrors();

    expect($faithful->refresh()->points)->toBe(2)
        ->and($traitor->refresh()->points)->toBe(1)
        ->and(CastMemberAction::query()->where('type', CastMemberActionType::FaithfulVotesTraitor)->count())->toBe(1)
        ->and(CastMemberAction::query()->where('type', CastMemberActionType::LoneVote)->count())->toBe(1)
        ->and(CastMemberAction::query()->where('type', CastMemberActionType::TraitorSurvivesVote)->count())->toBe(1);
});

test('multiple votes remove the lone vote bonus and award a surviving traitor per vote', function () {
    $episode = Episode::factory()->create();
    $faithfuls = CastMember::factory()->count(2)->create(['points' => 0]);
    $traitor = CastMember::factory()->create(['points' => 0, 'is_traitor' => true]);
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    foreach ($faithfuls as $faithful) {
        Livewire::test('pages::admin.scoring')
            ->set('episodeId', $episode->id)
            ->set('voterCastMemberId', $faithful->id)
            ->set('voteTargetCastMemberId', $traitor->id)
            ->call('saveVote')
            ->assertHasNoErrors();
    }

    expect($faithfuls[0]->refresh()->points)->toBe(1)
        ->and($faithfuls[1]->refresh()->points)->toBe(1)
        ->and($traitor->refresh()->points)->toBe(2)
        ->and(CastMemberAction::query()->where('type', CastMemberActionType::LoneVote)->count())->toBe(0);
});

test('a banished traitor does not receive survival points', function () {
    $traitor = CastMember::factory()->create(['points' => 0, 'is_traitor' => true]);
    $faithful = CastMember::factory()->create(['points' => 0]);
    $episode = Episode::factory()->create(['banished_cast_member_id' => $traitor->id]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('voterCastMemberId', $faithful->id)
        ->set('voteTargetCastMemberId', $traitor->id)
        ->call('saveVote')
        ->assertHasNoErrors();

    expect($traitor->refresh()->points)->toBe(0)
        ->and($faithful->refresh()->points)->toBe(2);
});

test('a voter disappears from the voter carousel after submitting a ballot', function () {
    $episode = Episode::factory()->create();
    $voter = CastMember::factory()->create(['points' => 0]);
    $traitor = CastMember::factory()->create(['points' => 0, 'is_traitor' => true]);
    $nextVoter = CastMember::factory()->create(['points' => 0]);
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->assertSeeHtml('vote-voter-'.$voter->id)
        ->set('voterCastMemberId', $voter->id)
        ->set('voteTargetCastMemberId', $traitor->id)
        ->call('saveVote')
        ->assertHasNoErrors()
        ->assertDontSeeHtml('vote-voter-'.$voter->id)
        ->assertSeeHtml('vote-voter-'.$nextVoter->id);

    expect(RoundTableVote::query()->count())->toBe(1);
});

test('a cast member cannot vote for themselves', function () {
    $episode = Episode::factory()->create();
    $castMember = CastMember::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test('pages::admin.scoring')
        ->set('episodeId', $episode->id)
        ->set('voterCastMemberId', $castMember->id)
        ->set('voteTargetCastMemberId', $castMember->id)
        ->call('saveVote')
        ->assertHasErrors(['voteTargetCastMemberId']);

    expect(RoundTableVote::query()->count())->toBe(0);
});
