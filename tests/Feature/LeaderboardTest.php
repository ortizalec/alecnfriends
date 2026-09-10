<?php

use App\Models\CastMember;
use App\Models\CastMemberAction;
use App\Models\Episode;
use App\Models\Poll;
use App\Models\PollResponse;
use App\Models\Prediction;
use App\Models\User;

test('leaderboard ranks player teams by their combined cast points', function () {
    $leader = User::factory()->create(['name' => 'First Place']);
    $runnerUp = User::factory()->create(['name' => 'Second Place']);
    $leader->castMembers()->attach(CastMember::factory()->count(5)->create(['points' => 10]));
    $runnerUp->castMembers()->attach(CastMember::factory()->count(5)->create(['points' => 5]));

    $this->actingAs($leader)
        ->get(route('dashboard'))
        ->assertSeeInOrder(['First Place', '50', 'Second Place', '25']);
});

test('leaderboard table shows player names team tags and total points without score breakdowns', function () {
    $user = User::factory()->create(['name' => 'Image Player']);
    $castMember = CastMember::factory()->create([
        'name' => 'Photo Teammate',
        'photo_url' => 'https://example.test/photo-teammate.png',
        'points' => 10,
    ]);
    $user->castMembers()->attach($castMember);
    Prediction::factory()->for($user)->create(['points' => 4]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('<table', false)
        ->assertSeeText('Image Player')
        ->assertSeeText('Photo Teammate')
        ->assertSee(route('cast-members.show', $castMember))
        ->assertDontSee('https://example.test/photo-teammate.png')
        ->assertSeeText('14')
        ->assertDontSeeText('1/5 selected')
        ->assertDontSeeText('4 prediction · 0 challenge');
});

test('my team displays cast members in points order', function () {
    $user = User::factory()->create();
    $lowerScorer = CastMember::factory()->create(['name' => 'Lower Scorer', 'points' => 2]);
    $higherScorer = CastMember::factory()->create(['name' => 'Higher Scorer', 'points' => 12]);
    $user->castMembers()->attach([$lowerScorer->id, $higherScorer->id]);

    $this->actingAs($user)
        ->get(route('team.edit'))
        ->assertSeeInOrder(['Higher Scorer', 'Lower Scorer'])
        ->assertSeeText('12 pts')
        ->assertSeeText('2 pts');
});

test('leaderboard includes prediction points in the total', function () {
    $user = User::factory()->create(['name' => 'Prediction Player']);
    $user->castMembers()->attach(CastMember::factory()->create(['points' => 10]));
    Prediction::factory()->for($user)->create(['points' => 4]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSeeText('14')
        ->assertDontSeeText('4 prediction');
});

test('dashboard prompts users to complete pending predictions and surveys', function () {
    $user = User::factory()->create();
    Episode::factory()->create(['predictions_are_open' => true, 'predictions_close_at' => now()->addHour()]);
    Poll::factory()->create(['is_published' => true]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSeeText('Your episode prediction is waiting')
        ->assertSeeText('survey needs your response');
});

test('dashboard does not prompt for an answered survey', function () {
    $user = User::factory()->create();
    $poll = Poll::factory()->create(['is_published' => true]);
    PollResponse::factory()->for($poll)->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertDontSeeText('survey needs your response');
});

test('dashboard shows the live cast action feed', function () {
    $user = User::factory()->create();
    $castMember = CastMember::factory()->create(['name' => 'Live Player']);
    CastMemberAction::factory()->for($castMember)->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSeeText('Live cast activity')
        ->assertSeeText('Live Player')
        ->assertSeeText('Earned challenge money');
});
