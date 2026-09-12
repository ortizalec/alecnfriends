<?php

namespace App\Models;

use Database\Factories\TraitorPredictionRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['is_open', 'first_traitor_cast_member_id', 'second_traitor_cast_member_id', 'third_traitor_cast_member_id', 'revealed_at'])]
class TraitorPredictionRound extends Model
{
    /** @use HasFactory<TraitorPredictionRoundFactory> */
    use HasFactory;

    /** @return HasMany<TraitorPrediction, $this> */
    public function predictions(): HasMany
    {
        return $this->hasMany(TraitorPrediction::class);
    }

    /** @return BelongsTo<CastMember, $this> */
    public function firstTraitor(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'first_traitor_cast_member_id');
    }

    /** @return BelongsTo<CastMember, $this> */
    public function secondTraitor(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'second_traitor_cast_member_id');
    }

    /** @return BelongsTo<CastMember, $this> */
    public function thirdTraitor(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'third_traitor_cast_member_id');
    }

    protected function casts(): array
    {
        return [
            'is_open' => 'boolean',
            'revealed_at' => 'datetime',
        ];
    }
}
