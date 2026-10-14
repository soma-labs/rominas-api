<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('placeholder')->nullable()->after('description');
            $table->text('eligibility_criteria')->nullable()->after('placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn(['placeholder', 'eligibility_criteria']);
        });
    }
};
