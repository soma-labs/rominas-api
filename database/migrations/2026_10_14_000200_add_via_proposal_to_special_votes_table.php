<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A member may now propose one entry (the proposal carries their vote) and also vote for one entry another
 * member proposed. `via_proposal` tells the two votes apart, and the unique key moves from one vote per
 * member per category to one of each kind.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('special_votes', function (Blueprint $table): void {
            $table->boolean('via_proposal')->default(false)->after('special_entry_id');
        });

        // The proposer's vote sits on their own entry, or, once that entry was merged, on its target.
        DB::table('special_votes')
            ->whereExists(function (Builder $query): void {
                $query->select(DB::raw(1))
                    ->from('special_entries')
                    ->whereColumn('special_entries.proposed_by_member_id', 'special_votes.member_id')
                    ->where(function (Builder $query): void {
                        $query->whereColumn('special_entries.id', 'special_votes.special_entry_id')
                            ->orWhere(function (Builder $query): void {
                                $query->where('special_entries.status', '=', 'merged')
                                    ->whereColumn('special_entries.merged_into_entry_id', 'special_votes.special_entry_id');
                            });
                    });
            })
            ->update(['via_proposal' => true]);

        // The new key leads with category_id too, so the category foreign key keeps an index once the
        // old one is dropped.
        Schema::table('special_votes', function (Blueprint $table): void {
            $table->unique(['category_id', 'member_id', 'via_proposal']);
        });

        Schema::table('special_votes', function (Blueprint $table): void {
            $table->dropUnique(['category_id', 'member_id']);
        });
    }

    public function down(): void
    {
        // The old key allows one vote per member per category: a member holding both keeps the proposal's.
        $proposers = DB::table('special_votes')
            ->where('via_proposal', '=', true)
            ->select(['category_id', 'member_id'])
            ->get();

        foreach ($proposers as $proposer) {
            DB::table('special_votes')
                ->where('category_id', '=', $proposer->category_id)
                ->where('member_id', '=', $proposer->member_id)
                ->where('via_proposal', '=', false)
                ->delete();
        }

        Schema::table('special_votes', function (Blueprint $table): void {
            $table->unique(['category_id', 'member_id']);
        });

        Schema::table('special_votes', function (Blueprint $table): void {
            $table->dropUnique(['category_id', 'member_id', 'via_proposal']);
            $table->dropColumn('via_proposal');
        });
    }
};
