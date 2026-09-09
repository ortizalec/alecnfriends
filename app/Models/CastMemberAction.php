<?php

namespace App\Models;

use App\CastMemberActionType;
use Database\Factories\CastMemberActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['episode_id', 'cast_member_id', 'created_by', 'round_table_vote_id', 'type', 'points'])]
class CastMemberAction extends Model
{
    /** @use HasFactory<CastMemberActionFactory> */
    use HasFactory;

    /** @return BelongsTo<CastMember, $this> */
    public function castMember(): BelongsTo
    {
        return $this->belongsTo(CastMember::class);
    }

    /** @return BelongsTo<Episode, $this> */
    public function episode(): BelongsTo
    {
        return $this->belongsTo(Episode::class);
    }

    protected function casts(): array
    {
        return ['type' => CastMemberActionType::class, 'points' => 'integer'];
    }
}
