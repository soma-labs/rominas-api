<?php

declare(strict_types=1);

namespace Rominas\Permissions\Model;

use Rominas\Permissions\Policies\PermissionPolicy;
use Rominas\Permissions\QueryBuilders\PermissionQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Spatie\Permission\Models\Permission as BasePermission;

/**
 * @mixin IdeHelperPermission
 */
#[UsePolicy(PermissionPolicy::class)]
class Permission extends BasePermission
{
    /**
     * @return PermissionQueryBuilder
     */
    public static function query(): PermissionQueryBuilder
    {
        /** @var PermissionQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): PermissionQueryBuilder
    {
        return new PermissionQueryBuilder($query);
    }
}
