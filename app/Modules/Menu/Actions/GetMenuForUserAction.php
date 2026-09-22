<?php

declare(strict_types=1);

namespace Rominas\Menu\Actions;

use Rominas\Users\Model\User;

class GetMenuForUserAction
{
    /**
     * Build the admin sidebar menu for the given user, dropping any item the user is not
     * permitted to see and any parent group left with no visible children. Recurses into
     * nested `children`.
     *
     * @return list<array<string, mixed>>
     */
    public function execute(User $user): array
    {
        /** @var list<array<string, mixed>> $menu */
        $menu = config('menus.admin_menu', []);

        return $this->filterItems($menu, $user);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filterItems(array $items, User $user): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (isset($item['permission']) && ! $user->can($item['permission'])) {
                continue;
            }

            if (isset($item['children'])) {
                /** @var list<array<string, mixed>> $children */
                $children = $item['children'];
                $item['children'] = $this->filterItems($children, $user);

                // A group whose children are all hidden becomes a dangling label — drop it.
                if ($item['children'] === []) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return $filtered;
    }
}
