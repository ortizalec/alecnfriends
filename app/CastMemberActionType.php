<?php

namespace App;

enum CastMemberActionType: string
{
    case Shield = 'shield';
    case ChallengeMoney = 'challenge_money';
    case FirstAccusation = 'first_accusation';
    case LoneVote = 'lone_vote';
    case FaithfulVotesTraitor = 'faithful_votes_traitor';
    case TraitorSurvivesVote = 'traitor_survives_vote';

    public function points(): int
    {
        return $this === self::Shield ? 2 : 1;
    }

    public function label(): string
    {
        return match ($this) {
            self::Shield => 'Earned a shield',
            self::ChallengeMoney => 'Earned challenge money',
            self::FirstAccusation => 'Made the first accusation',
            self::LoneVote => 'Cast the only vote for a player',
            self::FaithfulVotesTraitor => 'Faithful correctly voted for a traitor',
            self::TraitorSurvivesVote => 'Traitor received a vote and survived',
        };
    }
}
