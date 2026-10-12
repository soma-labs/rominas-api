<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('special_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->foreignId('special_entry_id')->constrained('special_entries')->cascadeOnDelete();
            $table->timestamps();

            // One action per member per category: a proposal carries the proposer's vote, so this single
            // key is what stops a member from both proposing and voting.
            $table->unique(['category_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_votes');
    }
};
