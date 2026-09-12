<?php

namespace App\Models;

use Database\Factories\TraitorPredictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['traitor_prediction_round_id', 'user_id', 'first_cast_member_id', 'second_cast_member_id', 'third_cast_member_id', 'points'])]
class TraitorPrediction extends Model
{
    /** @use HasFactory<TraitorPredictionFactory> */
    use HasFactory;

    /** @return BelongsTo<TraitorPredictionRound, $this> */
    public function round(): BelongsTo
    {
        return $this->belongsTo(TraitorPredictionRound::class, 'traitor_prediction_round_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CastMember, $this> */
    public function firstCastMember(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'first_cast_member_id');
    }

    /** @return BelongsTo<CastMember, $this> */
    public function secondCastMember(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'second_cast_member_id');
    }

    /** @return BelongsTo<CastMember, $this> */
    public function thirdCastMember(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'third_cast_member_id');
    }

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }
}
