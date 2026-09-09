<?php

namespace App\Models;

use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['question', 'maximum_selections', 'opens_at', 'closes_at', 'is_published'])]
class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    /** @return BelongsToMany<CastMember, $this> */
    public function responses(): BelongsToMany
    {
        return $this->belongsToMany(CastMember::class, 'poll_responses')->withPivot('user_id')->withTimestamps();
    }

    /** @return HasMany<PollResponse, $this> */
    public function pollResponses(): HasMany
    {
        return $this->hasMany(PollResponse::class);
    }

    /** @param Builder<Poll> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->where('is_published', true)
            ->where(fn (Builder $query) => $query->whereNull('opens_at')->orWhere('opens_at', '<=', now()))
            ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()));
    }

    protected function casts(): array
    {
        return ['opens_at' => 'datetime', 'closes_at' => 'datetime', 'is_published' => 'boolean'];
    }
}
