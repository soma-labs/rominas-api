<?php

declare(strict_types=1);

namespace Rominas\Menu\Controllers;

use Rominas\Menu\Actions\GetMenuCountsForUserAction;
use Rominas\Menu\Actions\GetMenuForUserAction;
use Rominas\Users\Model\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController
{
    /**
     * The sidebar menu for the authenticated admin, filtered to what they may see. Served to
     * the management dashboard so navigation and authorization share one source of truth.
     */
    public function menu(Request $request, GetMenuForUserAction $getMenuForUserAction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => $getMenuForUserAction->execute($user),
        ]);
    }

    /**
     * The sidebar badge counts for the authenticated admin, keyed by menu item id. Polled by the
     * management dashboard; a read, so the audit trail skips it.
     */
    public function counts(Request $request, GetMenuCountsForUserAction $getMenuCountsForUserAction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => (object) $getMenuCountsForUserAction->execute($user),
        ]);
    }
}
