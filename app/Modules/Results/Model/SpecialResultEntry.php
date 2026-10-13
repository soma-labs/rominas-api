<?php

declare(strict_types=1);

namespace Rominas\Results\Model;

use Database\Factories\SpecialResultEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Rominas\Categories\Model\Category;

/**
 * One entry's frozen standing in a special category of an edition whose results are published: its text,
 * the member votes it received (null in an appointed category) and its `position` (1 = winner; entries
 * tied on votes share it). The text is a copy, so the snapshot does not follow later edits of the entry.
 *
 * @mixin IdeHelperSpecialResultEntry
 */
#[Fillable([
    'result_snapshot_id',
    'category_id',
    'title',
    'votes',
    'position',
])]
class SpecialResultEntry extends Model
{
    /** @use HasFactory<SpecialResultEntryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'votes' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ResultSnapshot, $this>
     */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(ResultSnapshot::class, 'result_snapshot_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
