<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nominee_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('edition_id')->constrained('editions')->cascadeOnDelete();
            $table->string('nominee_type');
            // The name exactly as the member typed it (first occurrence kept).
            $table->string('raw_name');
            // Slug of the raw name — the dedup key that collapses spelling/case variants.
            $table->string('normalized_name');
            $table->string('status')->default('pending');
            // The canonical Catalog row id once linked/created (morph type = nominee_type). Null while pending.
            $table->unsignedBigInteger('resolved_nominee_id')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            // One row per distinct typed name, per type, per edition — resolve a name once.
            $table->unique(['edition_id', 'nominee_type', 'normalized_name'], 'nominee_submissions_dedup_unique');
            $table->index(['edition_id', 'status']);
            $table->index(['nominee_type', 'resolved_nominee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nominee_submissions');
    }
};
