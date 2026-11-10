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
        Schema::create('sandbox_payouts', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 64)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rt_id')->constrained('rt')->restrictOnDelete();
            $table->foreignId('service_area_id')->constrained('service_areas')->restrictOnDelete();
            $table->foreignId('balance_hold_id')->nullable()->constrained('balance_holds')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('channel', 16);
            $table->text('recipient');
            $table->text('request_payload');
            $table->string('payload_hash', 64);
            $table->uuid('reference_id')->unique();
            $table->uuid('provider_key')->unique();
            $table->string('provider_id', 100)->nullable()->unique();
            $table->string('business_id', 100)->nullable();
            $table->string('environment', 8)->default('test');
            $table->string('consent_version', 40);
            $table->timestamp('consented_at');
            $table->string('status', 20)->default('queued');
            $table->string('provider_status', 40)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->uuid('lease')->nullable();
            $table->timestamp('leased_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('ledger_entry_id')->nullable()->constrained('ledger_entries')->restrictOnDelete();
            $table->foreignId('reversal_entry_id')->nullable()->constrained('ledger_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'next_check_at']);
            $table->index(['customer_id', 'created_at']);
        });
        Schema::create('sandbox_payout_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sandbox_payout_id')->constrained('sandbox_payouts')->restrictOnDelete();
            $table->string('dedupe_key', 64)->unique();
            $table->string('event', 60);
            $table->string('provider_id', 100);
            $table->timestamp('created_at');
            $table->timestamp('processed_at')->nullable();
        });
        $identity = ['request_number', 'customer_id', 'rt_id', 'service_area_id', 'amount', 'channel', 'recipient', 'request_payload', 'payload_hash', 'reference_id', 'provider_key', 'environment', 'consent_version', 'consented_at', 'created_at'];
        $attachedOnce = ['balance_hold_id', 'provider_id', 'business_id', 'ledger_entry_id', 'reversal_entry_id'];
        if (DB::getDriverName() === 'sqlite') {
            $condition = implode(' OR ', array_map(static fn (string $field): string => "NEW.{$field} IS NOT OLD.{$field}", $identity));
            $condition .= ' OR '.implode(' OR ', array_map(static fn (string $field): string => "(OLD.{$field} IS NOT NULL AND NEW.{$field} IS NOT OLD.{$field})", $attachedOnce));
            DB::unprepared("CREATE TRIGGER sandbox_payouts_identity_update BEFORE UPDATE ON sandbox_payouts WHEN {$condition} BEGIN SELECT RAISE(ABORT, 'immutable payout identity'); END");
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER sandbox_payouts_valid_{$event} BEFORE {$event} ON sandbox_payouts WHEN NEW.environment != 'test' OR NEW.amount <= 0 OR NEW.status NOT IN ('queued','dispatching','pending','uncertain','succeeded','failed','reversed','cancelled') BEGIN SELECT RAISE(ABORT, 'invalid sandbox payout'); END");
            }
            foreach (['sandbox_payouts', 'sandbox_payout_events'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'historical payout record'); END");
            }
        } else {
            $condition = implode(' OR ', array_map(static fn (string $field): string => "NOT (NEW.{$field} <=> OLD.{$field})", $identity));
            $condition .= ' OR '.implode(' OR ', array_map(static fn (string $field): string => "(OLD.{$field} IS NOT NULL AND NOT (NEW.{$field} <=> OLD.{$field}))", $attachedOnce));
            DB::unprepared("CREATE TRIGGER sandbox_payouts_identity_update BEFORE UPDATE ON sandbox_payouts FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable payout identity'; END IF; END");
            DB::statement("ALTER TABLE sandbox_payouts ADD CONSTRAINT sandbox_payouts_valid CHECK (CAST(environment AS BINARY) = 'test' AND amount > 0 AND CAST(status AS BINARY) IN ('queued','dispatching','pending','uncertain','succeeded','failed','reversed','cancelled'))");
            foreach (['sandbox_payouts', 'sandbox_payout_events'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_prevent_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'historical payout record'");
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sandbox_payout_events');
        Schema::dropIfExists('sandbox_payouts');
    }
};
