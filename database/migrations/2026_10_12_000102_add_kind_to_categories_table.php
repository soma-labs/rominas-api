<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('kind')->default('ranked')->after('slug');
            // Only a ranked category accepts a Catalog entity type.
            $table->string('nominee_type')->nullable()->change();

            $table->index(['edition_id', 'kind']);
        });
    }

    public function down(): void
    {
        // The old schema has no place for a category that is not ranked.
        DB::table('categories')->where('kind', '!=', 'ranked')->delete();

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex(['edition_id', 'kind']);
            $table->string('nominee_type')->nullable(false)->change();
            $table->dropColumn('kind');
        });
    }
};
