<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('telegram_id', 20)->unique();
            $table->string('chat_id', 20);
            $table->string('account_fingerprint', 64);
            $table->uuid('generation');
            $table->timestamp('authorized_until');
            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('transaction_notifications')->default(false);
            $table->boolean('reminders')->default(false);
            $table->boolean('summaries')->default(false);
            $table->boolean('announcements')->default(false);
            $table->string('summary_frequency', 10)->default('weekly');
            $table->timestamp('blocked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('telegram_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('account_fingerprint', 64);
            $table->uuid('link_id')->nullable();
            $table->string('telegram_id', 20)->nullable();
            $table->string('display_name', 255)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        Schema::create('telegram_updates', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('telegram_id', 20)->index();
            $table->text('payload');
            $table->string('payload_hash', 64);
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code', 80)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('telegram_conversations', function (Blueprint $table): void {
            $table->string('telegram_id', 20)->primary();
            // Deliberately NOT nullOnDelete: revoked context must never become public context.
            $table->uuid('link_id')->nullable()->index();
            $table->string('state', 80)->default('menu');
            $table->unsignedBigInteger('last_update_id')->nullable();
            $table->text('data');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::create('telegram_actions', function (Blueprint $table): void {
            $table->string('id', 64)->primary();
            $table->string('telegram_id', 20)->index();
            $table->uuid('link_id')->nullable()->index();
            $table->uuid('generation')->nullable();
            $table->string('command', 80);
            $table->text('parameters');
            $table->unsignedBigInteger('consumed_by')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('telegram_outbox', function (Blueprint $table): void {
            $table->bigIncrements('sequence');
            $table->uuid('id')->unique();
            $table->string('dedupe_key', 191)->unique();
            $table->string('telegram_id', 20)->index();
            $table->uuid('link_id')->nullable()->index();
            $table->boolean('private')->default(true);
            $table->uuid('generation')->nullable();
            $table->string('category', 40)->default('reply');
            $table->string('kind', 24)->default('text');
            $table->text('payload');
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('lease')->nullable();
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('available_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('message_id', 30)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['telegram_outbox', 'telegram_actions', 'telegram_conversations', 'telegram_updates', 'telegram_challenges', 'telegram_links'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
