<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Rominas\Users\Model\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SUPER_ADMIN_EMAIL = 'superadmin@romias.ro';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);

        $this->seedSuperAdmin();
    }

    /**
     * Built without the factory: factories need Faker, which production (`composer install
     * --no-dev`) doesn't have. An existing super admin is left alone, so a re-run never resets
     * the password.
     */
    private function seedSuperAdmin(): void
    {
        $admin = User::query()->firstOrNew(['email' => self::SUPER_ADMIN_EMAIL]);

        if (! $admin->exists) {
            $password = app()->isLocal() ? 'password' : Str::password(24);

            $admin->forceFill([
                'name' => 'Super Admin',
                'email_verified_at' => now(),
                'password' => $password,
            ])->save();

            $this->command->warn(
                'Super admin created: ' . self::SUPER_ADMIN_EMAIL . ' / ' . $password . ' (shown only now, save it).',
            );
        }

        $admin->assignRole('super_admin');
    }
}
