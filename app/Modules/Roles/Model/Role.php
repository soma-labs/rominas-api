<?php

declare(strict_types=1);

namespace Rominas\Roles\Model;

use Rominas\Roles\Policies\RolePolicy;
use Rominas\Roles\QueryBuilders\RoleQueryBuilder;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Spatie\Permission\Models\Role as BaseRole;

/**
 * @mixin IdeHelperRole
 */
#[UsePolicy(RolePolicy::class)]
class Role extends BaseRole
{
    /**
     * @return RoleQueryBuilder
     */
    public static function query(): RoleQueryBuilder
    {
        /** @var RoleQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): RoleQueryBuilder
    {
        return new RoleQueryBuilder($query);
    }
}
