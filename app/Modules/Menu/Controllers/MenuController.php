<?php

declare(strict_types=1);

namespace Rominas\Menu\Controllers;

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
}
