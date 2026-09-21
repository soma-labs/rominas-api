<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Rominas\Permissions\Model\Permission;
use Rominas\Roles\Model\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Resource-level permissions checked by the simple policies. (User/role/permission
     * management stays super_admin-only for now.)
     *
     * @var list<string>
     */
    private const PERMISSIONS = [
        'editions',
        'categories',
        'members',
        'memberProposals',
        'shortlists',
        'results',
        'fraudMonitoring',
        'reporting',
        'nomineeSubmissions',
        'artists',
        'bands',
        'venues',
        'songs',
        'albums',
        'taxonomies',
        'taxonomyTerms',
        // Read the audit trail. Deliberately granted to NO role below → super_admin-only (via
        // Gate::before), so audited actors cannot read or scrub their own trail.
        'audit',
    ];

    /**
     * Baseline granted to the `admin` role (super_admin bypasses all via Gate::before). `results` is
     * deliberately excluded — viewing/exporting final results before publication is custodian-only.
     *
     * @var list<string>
     */
    private const ADMIN_GRANTS = [
        'editions',
        'categories',
        'members',
        'memberProposals',
        'shortlists',
        'reporting',
        'nomineeSubmissions',
        'artists',
        'bands',
        'venues',
        'songs',
        'albums',
        'taxonomies',
        'taxonomyTerms',
    ];

    /**
     * The custodian's extra rights: view/export an edition's complete final results before publication,
     * and review/cancel fraudulent public votes.
     *
     * @var list<string>
     */
    private const CUSTODIAN_GRANTS = [
        'results',
        'fraudMonitoring',
    ];

    /**
     * The fraud monitor's sole right: review an edition's public ballots and cancel fraudulent votes.
     * Shared with the custodian; deliberately NOT granted to `admin`.
     *
     * @var list<string>
     */
    private const FRAUD_MONITOR_GRANTS = [
        'fraudMonitoring',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::query()->firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();

        if ($admin !== null) {
            $admin->syncPermissions(self::ADMIN_GRANTS);
        }

        $custodian = Role::query()->where('name', 'custodian')->where('guard_name', 'web')->first();

        if ($custodian !== null) {
            $custodian->syncPermissions(self::CUSTODIAN_GRANTS);
        }

        $fraudMonitor = Role::query()->where('name', 'fraud_monitor')->where('guard_name', 'web')->first();

        if ($fraudMonitor !== null) {
            $fraudMonitor->syncPermissions(self::FRAUD_MONITOR_GRANTS);
        }
    }
}
