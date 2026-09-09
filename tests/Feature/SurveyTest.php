<?php

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login from surveys', function () {
    $this->get(route('surveys.index'))->assertRedirect(route('login'));
});

test('users see only currently open surveys', function () {
    $user = User::factory()->create();
    Poll::factory()->create(['question' => 'Open question', 'is_published' => true]);
    Poll::factory()->create(['question' => 'Draft question', 'is_published' => false]);
    Poll::factory()->create(['question' => 'Closed question', 'is_published' => true, 'closes_at' => now()->subMinute()]);

    $this->actingAs($user)->get(route('surveys.index'))
        ->assertSeeText('Open question')
        ->assertDontSeeText('Draft question')
        ->assertDontSeeText('Closed question');
});

test('a user can submit and revise a survey response', function () {
    $user = User::factory()->create();
    $poll = Poll::factory()->create(['is_published' => true, 'maximum_selections' => 2]);
    $castMembers = CastMember::factory()->count(3)->create();
    $this->actingAs($user);

    Livewire::test('pages::surveys')
        ->set("answers.$poll->id", [$castMembers[0]->id, $castMembers[1]->id])
        ->call('save', $poll->id)
        ->assertHasNoErrors()
        ->set("answers.$poll->id", [$castMembers[2]->id])
        ->call('save', $poll->id)
        ->assertHasNoErrors();

    expect(PollResponse::query()->whereBelongsTo($poll)->whereBelongsTo($user)->pluck('cast_member_id')->all())
        ->toBe([$castMembers[2]->id]);
});

test('survey responses enforce the choice limit and active cast', function () {
    $user = User::factory()->create();
    $poll = Poll::factory()->create(['is_published' => true, 'maximum_selections' => 1]);
    $active = CastMember::factory()->create();
    $murdered = CastMember::factory()->create(['status' => CastMemberStatus::Murdered, 'is_active' => false]);
    $this->actingAs($user);

    Livewire::test('pages::surveys')
        ->set("answers.$poll->id", [$active->id, $murdered->id])
        ->call('save', $poll->id)
        ->assertHasErrors(["answers.$poll->id"]);

    expect(PollResponse::query()->count())->toBe(0);
});

test('answered surveys show vote percentages and highlight the users choices', function () {
    $user = User::factory()->create();
    $otherUsers = User::factory()->count(2)->create();
    $poll = Poll::factory()->create(['is_published' => true, 'maximum_selections' => 2]);
    $firstCastMember = CastMember::factory()->create(['name' => 'First Choice']);
    $secondCastMember = CastMember::factory()->create(['name' => 'Second Choice']);
    PollResponse::factory()->for($poll)->for($user)->for($firstCastMember)->create();
    PollResponse::factory()->for($poll)->for($otherUsers[0])->for($firstCastMember)->create();
    PollResponse::factory()->for($poll)->for($otherUsers[0])->for($secondCastMember)->create();
    PollResponse::factory()->for($poll)->for($otherUsers[1])->for($secondCastMember)->create();

    $this->actingAs($user)->get(route('surveys.index'))
        ->assertSeeText('Survey results')
        ->assertSeeText('Based on 3 respondents')
        ->assertSeeTextInOrder(['First Choice', 'Your pick', '67%', '2 votes'])
        ->assertSeeTextInOrder(['Second Choice', '67%', '2 votes']);
});

test('survey results stay hidden until the user responds', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $poll = Poll::factory()->create(['is_published' => true]);
    PollResponse::factory()->for($poll)->for($otherUser)->create();

    $this->actingAs($user)->get(route('surveys.index'))
        ->assertDontSeeText('Survey results');
});
