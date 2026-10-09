<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ballots', function (Blueprint $table): void {
            // What the e-mail validation service said about the address when the link was requested, and
            // what we decided from it. All three are null when the address could not be checked. These are
            // the service's codes, not personal data.
            $table->string('email_check_status', 32)->nullable()->after('ip_hash');
            $table->string('email_check_sub_status', 64)->nullable()->after('email_check_status');
            $table->string('email_check_decision', 32)->nullable()->after('email_check_sub_status');

            $table->index(['edition_id', 'email_check_decision']);
        });
    }

    public function down(): void
    {
        Schema::table('ballots', function (Blueprint $table): void {
            $table->dropIndex(['edition_id', 'email_check_decision']);
            $table->dropColumn(['email_check_status', 'email_check_sub_status', 'email_check_decision']);
        });
    }
};
