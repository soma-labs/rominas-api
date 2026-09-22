<?php

declare(strict_types=1);

use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;
use Rominas\Users\Model\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

/**
 * Authenticate as a fresh role granted exactly the given permissions.
 *
 * @param  list<string>  $permissions
 */
function menuActingAs(array $permissions): User
{
    Role::query()->firstOrCreate(['name' => 'menu_tester', 'guard_name' => 'web']);

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    Role::query()->where('name', 'menu_tester')->where('guard_name', 'web')->firstOrFail()
        ->syncPermissions($permissions);

    $user = User::factory()->create();
    $user->assignRole('menu_tester');
    Sanctum::actingAs($user);

    return $user;
}

it('returns the full menu to a super_admin', function (): void {
    actingAsSuperAdmin();

    getJson('/api/admin/menu')
        ->assertStatus(200)
        ->assertJsonFragment(['id' => 'sidenav.overview'])
        ->assertJsonFragment(['id' => 'sidenav.access'])
        ->assertJsonFragment(['id' => 'sidenav.audit']);
});

it('hides items the user lacks permission for and keeps the ones they have', function (): void {
    menuActingAs(['taxonomies']);

    $response = getJson('/api/admin/menu')->assertStatus(200);

    // Ungated item is always present.
    $response->assertJsonFragment(['id' => 'sidenav.overview']);
    // Access control is gated on the super_admin-only `permissions` — hidden here.
    $response->assertJsonMissing(['id' => 'sidenav.access']);
    // The taxonomy group shows, but only the child whose permission the user holds.
    $response->assertJsonFragment(['id' => 'sidenav.taxonomy']);
    $response->assertJsonFragment(['id' => 'sidenav.taxonomies']);
    $response->assertJsonMissing(['id' => 'sidenav.taxonomy-terms']);
    // A domain module the user has no permission for is absent.
    $response->assertJsonMissing(['id' => 'sidenav.editions']);
});

it('drops a group left with no visible children', function (): void {
    config([
        'menus.admin_menu' => [
            [
                'id' => 'sidenav.group',
                'label' => 'Group',
                // No permission on the parent, but its only child is gated.
                'children' => [
                    ['id' => 'sidenav.child', 'label' => 'Child', 'url' => '/child', 'permission' => 'editions'],
                ],
            ],
        ],
    ]);

    menuActingAs(['taxonomies']);

    getJson('/api/admin/menu')
        ->assertStatus(200)
        ->assertJsonMissing(['id' => 'sidenav.group'])
        ->assertJsonMissing(['id' => 'sidenav.child']);
});

it('rejects an unauthenticated request with 401', function (): void {
    getJson('/api/admin/menu')->assertStatus(401);
});
