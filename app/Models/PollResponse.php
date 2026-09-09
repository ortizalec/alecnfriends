<?php

namespace App\Models;

use Database\Factories\PollResponseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['poll_id', 'user_id', 'cast_member_id'])]
class PollResponse extends Model
{
    /** @use HasFactory<PollResponseFactory> */
    use HasFactory;

    /** @return BelongsTo<Poll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<CastMember, $this> */
    public function castMember(): BelongsTo
    {
        return $this->belongsTo(CastMember::class);
    }
}
