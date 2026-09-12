<?php

test('the landing page explains the player experience and implemented scoring rules', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSeeText('Build your team.')
        ->assertSeeText('Choose exactly five active cast members')
        ->assertSeeText('Surveys do not add leaderboard points.')
        ->assertSeeText('Earns a shield')
        ->assertSeeText('Correctly predicts first out at breakfast')
        ->assertSeeText('Each correct traitor')
        ->assertSeeText('Per qualifying episode')
        ->assertDontSeeText('Admin Account')
        ->assertDontSeeText('20 points');
});
