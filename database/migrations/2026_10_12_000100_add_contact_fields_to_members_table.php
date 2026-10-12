<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('position')->nullable()->after('email');
            $table->string('company')->nullable()->after('position');
            $table->string('phone')->nullable()->after('company');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->dropColumn(['position', 'company', 'phone']);
        });
    }
};
