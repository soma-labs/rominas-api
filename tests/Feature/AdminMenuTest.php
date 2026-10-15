<?php

declare(strict_types=1);

use Rominas\Academy\MemberProposal\Model\MemberProposal;
use Rominas\Audit\Model\AuditLog;
use Rominas\Catalog\NomineeSubmission\Model\NomineeSubmission;
use Rominas\Editions\Enums\EditionStatus;
use Rominas\Editions\Model\Edition;
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

it('counts the active edition\'s pending nominee submissions and the pending member proposals', function (): void {
    $activeEdition = Edition::factory()->status(EditionStatus::NominationsClosed)->create();
    $archivedEdition = Edition::factory()->archived()->create();

    NomineeSubmission::factory()->count(2)->create(['edition_id' => $activeEdition->id]);
    NomineeSubmission::factory()->resolved()->create(['edition_id' => $activeEdition->id]);
    NomineeSubmission::factory()->rejected()->create(['edition_id' => $activeEdition->id]);
    NomineeSubmission::factory()->create(['edition_id' => $archivedEdition->id]);

    MemberProposal::factory()->count(3)->create();
    MemberProposal::factory()->approved()->create();
    MemberProposal::factory()->rejected()->create();

    menuActingAs(['nomineeSubmissions', 'memberProposals']);

    getJson('/api/admin/menu/counts')
        ->assertStatus(200)
        ->assertExactJson(['data' => ['sidenav.editions' => 2, 'sidenav.member-proposals' => 3]]);
});

it('counts no nominee submissions when there is no active edition', function (): void {
    $archivedEdition = Edition::factory()->archived()->create();
    NomineeSubmission::factory()->create(['edition_id' => $archivedEdition->id]);

    menuActingAs(['nomineeSubmissions']);

    getJson('/api/admin/menu/counts')
        ->assertStatus(200)
        ->assertExactJson(['data' => ['sidenav.editions' => 0]]);
});

it('leaves out the counts the user may not see', function (): void {
    MemberProposal::factory()->create();

    menuActingAs(['taxonomies']);

    getJson('/api/admin/menu/counts')
        ->assertStatus(200)
        ->assertExactJson(['data' => []]);
});

it('does not record the counts poll in the audit trail', function (): void {
    menuActingAs(['memberProposals']);

    getJson('/api/admin/menu/counts')->assertStatus(200);

    expect(AuditLog::query()->count())->toBe(0);
});

it('rejects an unauthenticated counts request with 401', function (): void {
    getJson('/api/admin/menu/counts')->assertStatus(401);
});
