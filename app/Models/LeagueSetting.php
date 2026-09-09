<?php

namespace App\Models;

use Database\Factories\LeagueSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['team_selection_open'])]
class LeagueSetting extends Model
{
    /** @use HasFactory<LeagueSettingFactory> */
    use HasFactory;

    public static function teamSelectionIsOpen(): bool
    {
        return self::query()->value('team_selection_open') ?? true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'team_selection_open' => 'boolean',
        ];
    }
}
