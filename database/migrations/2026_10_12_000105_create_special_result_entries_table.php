<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('special_result_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('result_snapshot_id')->constrained('result_snapshots')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            // The entry's text is copied, not referenced: the snapshot must not change if the entry does.
            $table->string('title');
            $table->text('description')->nullable();
            // Null for an appointed category, where nobody voted.
            $table->unsignedInteger('votes')->nullable();
            // Not unique per category: entries tied on votes share a position.
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['result_snapshot_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_result_entries');
    }
};
