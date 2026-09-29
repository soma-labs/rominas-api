<?php

declare(strict_types=1);

namespace Rominas\Audit\Model;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Rominas\Audit\Policies\AuditLogPolicy;
use Rominas\Audit\QueryBuilders\AuditLogQueryBuilder;

/**
 * One audited action: who did what, to which resource, with what result. Rows are written by the
 * {@see \Rominas\Audit\Middleware\RecordAuditTrail} middleware from the HTTP request/response and are
 * append-only (no update/delete). The actor is polymorphic — `causer_type` ('user' | 'member' | null) +
 * `causer_id`, with `causer_label` snapshotting the actor's name so the row stays legible after the actor
 * is deleted; `context` holds the redacted request payload plus any Context-supplied extras.
 *
 * @mixin IdeHelperAuditLog
 */
#[Fillable([
    'causer_type',
    'causer_id',
    'causer_label',
    'action',
    'method',
    'subject_type',
    'subject_id',
    'status_code',
    'context',
    'ip_address',
    'user_agent',
])]
#[UsePolicy(AuditLogPolicy::class)]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * @return AuditLogQueryBuilder
     */
    public static function query(): AuditLogQueryBuilder
    {
        /** @var AuditLogQueryBuilder $builder */
        $builder = parent::query();

        return $builder;
    }

    public function newEloquentBuilder($query): AuditLogQueryBuilder
    {
        return new AuditLogQueryBuilder($query);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'status_code' => 'integer',
        ];
    }
}
