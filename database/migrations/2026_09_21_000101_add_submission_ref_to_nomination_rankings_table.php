<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('nomination_rankings', function (Blueprint $table): void {
            // A free-text pick points at its staged submission; nominee_id is backfilled on reconciliation.
            $table->foreignId('nominee_submission_id')
                ->nullable()
                ->after('category_id')
                ->constrained('nominee_submissions')
                ->cascadeOnDelete();

            // Null until the submission is resolved to a canonical Catalog row.
            $table->unsignedBigInteger('nominee_id')->nullable()->change();

            // No duplicate typed pick within a ballot's category. (The pre-existing
            // (nomination, category, nominee_type, nominee_id) unique still guards resolved duplicates —
            // MySQL treats the many NULL nominee_id values as distinct while picks are pending.)
            $table->unique(['nomination_id', 'category_id', 'nominee_submission_id'], 'nomination_rankings_unique_submission');
        });
    }

    public function down(): void
    {
        Schema::table('nomination_rankings', function (Blueprint $table): void {
            $table->dropUnique('nomination_rankings_unique_submission');
            $table->dropConstrainedForeignId('nominee_submission_id');
            $table->unsignedBigInteger('nominee_id')->nullable(false)->change();
        });
    }
};
