<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('result_snapshots', function (Blueprint $table): void {
            // The scoring algorithm in force when results were published — decides what the stored share /
            // final fields mean, so the snapshot stays readable after the config is switched.
            $table->string('algorithm', 32)->default('attributed')->after('public_vote_weight');
        });
    }

    public function down(): void
    {
        Schema::table('result_snapshots', function (Blueprint $table): void {
            $table->dropColumn('algorithm');
        });
    }
};
