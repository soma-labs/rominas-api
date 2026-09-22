<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Rominas\Users\Model\User;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@rominas.ro',
        ]);

        $admin->assignRole('super_admin');
    }
}
