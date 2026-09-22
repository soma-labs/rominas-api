<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin menu
    |--------------------------------------------------------------------------
    |
    | The sidebar menu served to the management dashboard via GET /api/admin/menu.
    | Each item is filtered per-user by GetMenuForUserAction: an item with a
    | `permission` is only returned when the user passes `can(permission)`
    | (super_admin bypasses via Gate::before), and a group left with no visible
    | children is dropped. Items may nest via `children`.
    |
    | Item shape: ['id' => string, 'label' => string, 'url' => ?string,
    |              'permission' => ?string, 'children' => ?array]
    |
    | Permission names match database/seeders/PermissionSeeder.php. NOTE: user,
    | role and permission administration is currently super_admin-only (those
    | permissions are seeded to no role), so the "Access control" section is gated
    | on `permissions` — a super_admin-only permission — until finer-grained user
    | permissions are granted to other roles.
    |
    */

    'admin_menu' => [
        [
            'id' => 'sidenav.overview',
            'label' => 'Overview',
            'url' => '/',
        ],
        // ------------------------------------------------------------------
        // Domain modules. The admin pages for these arrive in later phases;
        // the nav entries are gated by their real permissions so they light up
        // per role as the frontend catches up.
        // ------------------------------------------------------------------
        [
            'id' => 'sidenav.editions',
            'label' => 'Editions',
            'url' => '/editions',
            'permission' => 'editions',
        ],
        [
            'id' => 'sidenav.categories',
            'label' => 'Categories',
            'url' => '/categories',
            'permission' => 'categories',
        ],
        [
            'id' => 'sidenav.academy',
            'label' => 'Academy',
            'permission' => 'members',
            'children' => [
                [
                    'id' => 'sidenav.members',
                    'label' => 'Members',
                    'url' => '/members',
                    'permission' => 'members',
                ],
                [
                    'id' => 'sidenav.member-proposals',
                    'label' => 'Member proposals',
                    'url' => '/member-proposals',
                    'permission' => 'memberProposals',
                ],
            ],
        ],
        [
            'id' => 'sidenav.results',
            'label' => 'Results',
            'url' => '/results',
            'permission' => 'results',
        ],
        [
            'id' => 'sidenav.fraud-monitoring',
            'label' => 'Fraud monitoring',
            'url' => '/fraud-monitoring',
            'permission' => 'fraudMonitoring',
        ],
        [
            'id' => 'sidenav.reporting',
            'label' => 'Reporting',
            'url' => '/reporting',
            'permission' => 'reporting',
        ],
        [
            'id' => 'sidenav.audit',
            'label' => 'Audit log',
            'url' => '/audit-logs',
            'permission' => 'audit',
        ],
        [
            'id' => 'sidenav.catalog',
            'label' => 'Catalog',
            'permission' => 'artists',
            'children' => [
                [
                    'id' => 'sidenav.artists',
                    'label' => 'Artists',
                    'url' => '/artists',
                    'permission' => 'artists',
                ],
                [
                    'id' => 'sidenav.bands',
                    'label' => 'Bands',
                    'url' => '/bands',
                    'permission' => 'bands',
                ],
                [
                    'id' => 'sidenav.venues',
                    'label' => 'Venues',
                    'url' => '/venues',
                    'permission' => 'venues',
                ],
                [
                    'id' => 'sidenav.songs',
                    'label' => 'Songs',
                    'url' => '/songs',
                    'permission' => 'songs',
                ],
                [
                    'id' => 'sidenav.albums',
                    'label' => 'Albums',
                    'url' => '/albums',
                    'permission' => 'albums',
                ],
            ],
        ],
        [
            'id' => 'sidenav.access',
            'label' => 'Access control',
            'permission' => 'permissions',
            'children' => [
                [
                    'id' => 'sidenav.users',
                    'label' => 'Users',
                    'url' => '/users',
                    'permission' => 'permissions',
                ],
                [
                    'id' => 'sidenav.roles',
                    'label' => 'Roles',
                    'url' => '/roles',
                    'permission' => 'roles',
                ],
                [
                    'id' => 'sidenav.permissions',
                    'label' => 'Permissions',
                    'url' => '/permissions',
                    'permission' => 'permissions',
                ],
            ],
        ],
        [
            'id' => 'sidenav.taxonomy',
            'label' => 'Taxonomy',
            'permission' => 'taxonomies',
            'children' => [
                [
                    'id' => 'sidenav.taxonomies',
                    'label' => 'Taxonomies',
                    'url' => '/taxonomies',
                    'permission' => 'taxonomies',
                ],
                [
                    'id' => 'sidenav.taxonomy-terms',
                    'label' => 'Terms',
                    'url' => '/taxonomy-terms',
                    'permission' => 'taxonomyTerms',
                ],
            ],
        ],
    ],
];
