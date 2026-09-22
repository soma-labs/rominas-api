<?php

declare(strict_types=1);

use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

it('returns the authenticated admin with roles and permissions', function (): void {
    $user = actingAsSuperAdmin();

    getJson('/api/admin/me')
        ->assertStatus(200)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonStructure([
            'data' => ['id', 'email', 'name', 'roles', 'permissions'],
        ]);
});

it('exposes the effective permissions granted through the user\'s role', function (): void {
    Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'taxonomies', 'guard_name' => 'web']);
    Role::query()->where('name', 'admin')->where('guard_name', 'web')->firstOrFail()
        ->givePermissionTo('taxonomies');

    $user = User::factory()->create();
    $user->assignRole('admin');
    Sanctum::actingAs($user);

    getJson('/api/admin/me')
        ->assertStatus(200)
        ->assertJsonFragment(['name' => 'taxonomies']);
});

it('rejects an unauthenticated request with 401', function (): void {
    getJson('/api/admin/me')->assertStatus(401);
});
