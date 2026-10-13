<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('special_entries', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::table('special_result_entries', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }

    public function down(): void
    {
        Schema::table('special_entries', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('normalized_title');
        });

        Schema::table('special_result_entries', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('title');
        });
    }
};
