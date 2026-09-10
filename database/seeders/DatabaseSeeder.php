<?php

namespace Database\Seeders;

use App\CastMemberStatus;
use App\Models\CastMember;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);

        $castNames = [
            'Alexis Reed', 'Blake Turner', 'Cameron Wells', 'Dakota Hayes', 'Emerson Cole',
            'Finley Brooks', 'Gray Monroe', 'Harper Quinn', 'Indigo Price', 'Jordan Rivers',
            'Kai Morgan', 'Logan Ellis', 'Morgan Lane', 'Noah Bennett', 'Oakley James',
            'Parker Sage', 'Quinn Taylor', 'Riley Stone', 'Sawyer Dean', 'Taylor Blake',
            'Winter Clarke', 'Zion Hart',
        ];

        $castMembersNeeded = max(0, 22 - CastMember::query()->count());

        collect($castNames)->take($castMembersNeeded)->values()->each(function (string $name, int $index): void {
            CastMember::query()->updateOrCreate(
                ['name' => $name],
                [
                    'photo_url' => 'https://picsum.photos/seed/faithful-'.str($name)->slug().'/400/400',
                    'is_active' => true,
                    'status' => CastMemberStatus::Active,
                    'is_traitor' => false,
                    'points' => ($index * 7 + 3) % 18,
                ],
            );
        });

        $castMembers = CastMember::query()->oldest('id')->take(22)->get();

        $playerNames = [
            'Avery Parker', 'Bailey Brooks', 'Casey Morgan', 'Devon Reed',
            'Elliot Hayes', 'Frankie Lane', 'Jamie Stone', 'Kendall Rivers',
            'Micah Wells', 'Reese Clarke', 'Robin Taylor', 'Skyler Quinn',
        ];

        foreach ($playerNames as $index => $name) {
            $player = User::query()->updateOrCreate(
                ['email' => 'player'.($index + 1).'@example.com'],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'email_verified_at' => Carbon::now(),
                    'is_admin' => false,
                ],
            );

            $team = collect(range(0, 4))
                ->map(fn (int $offset): int => $castMembers[($index * 2 + $offset) % $castMembers->count()]->id)
                ->all();
            $player->castMembers()->sync($team);
        }

        $this->call(DemoSeasonSeeder::class);
    }
}
