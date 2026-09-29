<?php

declare(strict_types=1);

namespace Rominas\Academy\Member\Model;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Rominas\Academy\Member\Enums\MemberStatus;
use Rominas\Academy\Member\Policies\MemberPolicy;
use Rominas\Academy\Member\QueryBuilders\MemberQueryBuilder;

/**
 * An academy member — a passwordless participant account authenticated via magic link on the
 * `member` Sanctum guard (never the admin `web` guard). A member token can never authenticate as an
 * admin `User` and vice-versa (Sanctum scopes the tokenable to the guard's provider model).
 *
 * @mixin IdeHelperMember
 */
#[Fillable([
    'name',
    'email',
    'status',
    'invited_at',
    'activated_at',
])]
#[Hidden([
    'remember_token',
])]
#[UsePolicy(MemberPolicy::class)]
class Member extends Authenticatable
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;
    use HasApiTokens;
    use Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MemberStatus::class,
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return MemberQueryBuilder
     */
    public static function query(): MemberQueryBuilder
    {
        /** @var MemberQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): MemberQueryBuilder
    {
        return new MemberQueryBuilder($query);
    }
}
