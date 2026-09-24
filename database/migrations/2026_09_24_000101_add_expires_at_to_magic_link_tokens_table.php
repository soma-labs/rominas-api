<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Every link issued before this migration had the old fixed lifetime. */
    private const int LEGACY_LIFETIME_MINUTES = 15;

    public function up(): void
    {
        Schema::table('magic_link_tokens', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('token');
        });

        // Links already in flight keep the lifetime they were issued with. The table holds at most one
        // row per member per guard, so a per-row update is fine.
        foreach (DB::table('magic_link_tokens')->get() as $row) {
            $issuedAt = $row->created_at === null ? Carbon::now() : Carbon::parse($row->created_at);

            DB::table('magic_link_tokens')
                ->where('email', $row->email)
                ->where('guard', $row->guard)
                ->update(['expires_at' => $issuedAt->copy()->addMinutes(self::LEGACY_LIFETIME_MINUTES)]);
        }

        Schema::table('magic_link_tokens', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('magic_link_tokens', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
