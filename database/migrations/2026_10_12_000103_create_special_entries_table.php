<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('special_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('title');
            // The title as the duplicate check compares it (see NomineeNameNormalizer).
            $table->string('normalized_title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            // Null when an admin entered the entry (the result of an appointed category).
            $table->foreignId('proposed_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignId('merged_into_entry_id')->nullable()->constrained('special_entries')->nullOnDelete();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['category_id', 'normalized_title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_entries');
    }
};
