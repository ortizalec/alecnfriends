<?php

namespace App;

enum CastMemberStatus: string
{
    case Active = 'active';
    case Murdered = 'murdered';
    case Banished = 'banished';
}
