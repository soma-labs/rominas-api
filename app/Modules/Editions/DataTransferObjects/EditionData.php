<?php

declare(strict_types=1);

namespace Rominas\Editions\DataTransferObjects;

use Illuminate\Support\Carbon;

class EditionData
{
    public function __construct(
        public string $name,
        public Carbon $starts_at,
        public Carbon $nominations_start_at,
        public Carbon $nominations_end_at,
        public Carbon $voting_start_at,
        public Carbon $voting_end_at,
        public Carbon $ends_at,
        // Null = "not supplied on this request" → leave the edition's current weights untouched
        // (create ignores these and falls back to the DB defaults 60/40).
        public ?int $academy_vote_weight = null,
        public ?int $public_vote_weight = null,
        // Null = "not supplied" → keep the current slug (create always derives it from the name).
        public ?string $slug = null,
    ) {}
}
