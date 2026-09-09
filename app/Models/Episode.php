<?php

namespace App\Models;

use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['number', 'title', 'airs_at', 'predictions_close_at', 'predictions_are_open', 'murdered_cast_member_id', 'banished_cast_member_id', 'breakfast_cast_member_id'])]
class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory;

    /** @return HasMany<Prediction, $this> */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    /** @return HasMany<CastMemberAction, $this> */
    public function castMemberActions(): HasMany
    {
        return $this->hasMany(CastMemberAction::class);
    }

    /** @return HasMany<RoundTableVote, $this> */
    public function roundTableVotes(): HasMany
    {
        return $this->hasMany(RoundTableVote::class);
    }

    protected function casts(): array
    {
        return ['airs_at' => 'datetime', 'predictions_close_at' => 'datetime', 'predictions_are_open' => 'boolean'];
    }
}
