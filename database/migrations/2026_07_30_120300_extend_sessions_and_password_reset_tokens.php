<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Keep both indexes intentionally: Laravel's baseline user_id index serves user-only lookups,
            // while this composite index serves per-user activity ordering/range queries.
            $table->index(['user_id', 'last_activity'], 'sessions_user_activity_index');
            $table->timestamp('expires_at')->nullable()->index('sessions_expires_at_index');
            $table->char('ip_address_hash', 64)->nullable()->index('sessions_ip_hash_index');
        });
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->index()->constrained('users')->cascadeOnDelete();
            $table->string('phone', 20)->nullable()->index();
            $table->unique('token', 'password_reset_tokens_token_unique');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('used_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['phone']);
            $table->dropUnique('password_reset_tokens_token_unique');
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['used_at']);
            $table->dropColumn(['user_id', 'phone', 'expires_at', 'used_at']);
        });
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->dropIndex('sessions_user_activity_index');
            $table->dropIndex('sessions_expires_at_index');
            $table->dropIndex('sessions_ip_hash_index');
            $table->dropColumn(['expires_at', 'ip_address_hash']);
        });
    }
};
