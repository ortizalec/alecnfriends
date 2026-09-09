<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = config('app.admin.email');
        $password = config('app.admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD before creating the admin account.');
        }

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->name = config('app.admin.name');
        $admin->password = Hash::make($password);
        $admin->email_verified_at = Carbon::now();
        $admin->is_admin = true;
        $admin->save();
    }
}
