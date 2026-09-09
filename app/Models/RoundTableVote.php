<?php

namespace App\Models;

use Database\Factories\RoundTableVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['episode_id', 'voter_cast_member_id', 'target_cast_member_id', 'created_by'])]
class RoundTableVote extends Model
{
    /** @use HasFactory<RoundTableVoteFactory> */
    use HasFactory;

    /** @return BelongsTo<Episode, $this> */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    /** @return BelongsTo<CastMember, $this> */
    public function voter(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'voter_cast_member_id');
    }

    /** @return BelongsTo<CastMember, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CastMember::class, 'target_cast_member_id');
    }
}
