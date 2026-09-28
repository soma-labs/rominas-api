<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('result_snapshots', function (Blueprint $table): void {
            $table->id();
            // One immutable snapshot per edition, frozen when the edition's results are published.
            $table->foreignId('edition_id')->unique()->constrained('editions')->cascadeOnDelete();
            // The weighting in force when results are published, captured so the snapshot is self-describing.
            $table->unsignedTinyInteger('academy_vote_weight');
            $table->unsignedTinyInteger('public_vote_weight');
            $table->timestamp('published_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_snapshots');
    }
};
