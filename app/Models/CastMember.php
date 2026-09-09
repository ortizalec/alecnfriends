<?php

namespace App\Models;

use App\CastMemberStatus;
use Database\Factories\CastMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'photo_url', 'photo_path', 'is_active', 'status', 'is_traitor', 'points'])]
class CastMember extends Model
{
    /** @use HasFactory<CastMemberFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * @param  Builder<CastMember>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', CastMemberStatus::Active);
    }

    public function imageUrl(): string
    {
        if ($this->photo_path) {
            return Storage::disk('public')->url($this->photo_path);
        }

        return $this->photo_url ?: 'https://picsum.photos/seed/faithful-'.$this->getKey().'/400/400';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'points' => 'integer',
            'status' => CastMemberStatus::class,
            'is_traitor' => 'boolean',
        ];
    }
}
