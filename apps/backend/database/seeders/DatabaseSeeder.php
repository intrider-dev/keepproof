<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Accounts\Actions\RegisterAccount;
use App\Modules\Accounts\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (User::query()->where('email', 'demo@keepproof.local')->exists()) {
            return;
        }

        $register = app(RegisterAccount::class);
        $user = $register->execute(
            name: 'Demo User',
            email: 'demo@keepproof.local',
            password: 'KeepProof!2026',
            timezone: 'UTC',
            locale: 'en',
        );

        $user->forceFill(['email_verified_at' => now()])->save();
    }
}
