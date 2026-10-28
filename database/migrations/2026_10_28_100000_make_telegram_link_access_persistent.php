<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retain legacy columns for rollback, not as a future expiry or idle timer.
        Schema::table('telegram_links', function (Blueprint $table): void {
            $table->timestamp('authorized_until')->nullable()->change();
        });
        DB::table('telegram_links')->update(['authorized_until' => null, 'last_activity_at' => null]);
    }

    public function down(): void
    {
        // Rolling back to timed access must require fresh web authorization.
        DB::table('telegram_links')->whereNull('authorized_until')->update(['authorized_until' => now()]);
        Schema::table('telegram_links', function (Blueprint $table): void {
            $table->timestamp('authorized_until')->nullable(false)->change();
        });
    }
};
