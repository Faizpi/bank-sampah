<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('status', 30)->default('aktif');
            $table->string('currency', 3)->default('IDR');
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('deposits', function (Blueprint $table): void {
            $table->id();
            $table->string('deposit_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->string('method', 30);
            $table->string('location', 255)->nullable();
            $table->dateTime('occurred_at');
            $table->string('status', 30)->default('draf');
            $table->decimal('total_weight_kg', 15, 3)->nullable();
            $table->unsignedBigInteger('total_value')->nullable();
            $table->dateTime('finalized_at')->nullable();
            $table->string('idempotency_key', 191)->nullable()->unique();
            $table->char('verification_token_hash', 64)->nullable()->unique();
            $table->text('verification_token_encrypted')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'occurred_at']);
            $table->index(['staff_id', 'occurred_at']);
            $table->index(['status', 'occurred_at']);
        });

        Schema::create('deposit_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('deposit_id')->constrained('deposits')->restrictOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
            $table->foreignId('waste_condition_id')->constrained('waste_conditions')->restrictOnDelete();
            $table->string('waste_type_code', 30)->nullable();
            $table->string('waste_type_name', 120)->nullable();
            $table->string('unit_code', 30)->nullable();
            $table->string('unit_name', 120)->nullable();
            $table->string('unit_symbol', 30)->nullable();
            $table->string('condition_code', 30)->nullable();
            $table->string('condition_name', 120)->nullable();
            $table->decimal('weight_kg', 15, 3);
            $table->unsignedBigInteger('price_per_unit')->nullable();
            $table->unsignedBigInteger('subtotal')->nullable();
            $table->string('rounding_version', 30)->nullable();
            $table->json('price_snapshot')->nullable();
            $table->timestamps();
            $table->index(['deposit_id', 'waste_type_id', 'waste_condition_id'], 'deposit_items_lookup_index');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('scope', 120);
            $table->string('key', 191);
            $table->char('payload_hash', 64);
            $table->string('status', 30)->default('processing');
            $table->string('result_type', 160)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'scope', 'key'], 'idempotency_actor_scope_key_unique');
            $table->index('expires_at');
        });

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('direction', 10);
            $table->string('kind', 80);
            $table->unsignedBigInteger('amount');
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('source_key', 191)->unique();
            $table->foreignId('related_entry_id')->nullable()->constrained('ledger_entries')->restrictOnDelete();
            $table->dateTime('effective_at');
            $table->unsignedBigInteger('balance_after')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id', 'kind'], 'ledger_source_kind_unique');
            $table->index(['ledger_account_id', 'effective_at'], 'ledger_account_effective_index');
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('balance_holds', function (Blueprint $table): void {
            $table->id();
            $table->string('hold_number', 40)->unique();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('source_type', 120);
            $table->unsignedBigInteger('source_id');
            $table->string('source_key', 191)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('aktif');
            $table->dateTime('held_at');
            $table->dateTime('released_at')->nullable();
            $table->dateTime('converted_at')->nullable();
            $table->timestamps();
            $table->unique(['source_type', 'source_id'], 'balance_holds_source_unique');
            $table->index(['ledger_account_id', 'status']);
        });

        Schema::create('transaction_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('correction_number', 40)->unique();
            $table->foreignId('deposit_id')->constrained('deposits')->restrictOnDelete();
            $table->text('reason');
            $table->json('before_values');
            $table->json('after_values');
            $table->bigInteger('delta_value');
            $table->string('status', 30)->default('final');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at');
            $table->timestamps();
            $table->index(['deposit_id', 'created_at']);
        });

        Schema::create('transaction_reversals', function (Blueprint $table): void {
            $table->id();
            $table->string('reversal_number', 40)->unique();
            $table->foreignId('original_deposit_id')->constrained('deposits')->restrictOnDelete();
            $table->foreignId('original_entry_id')->constrained('ledger_entries')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('finalized_at');
            $table->timestamps();
            $table->unique('original_deposit_id', 'transaction_reversals_original_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_reversals');
        Schema::dropIfExists('transaction_corrections');
        Schema::dropIfExists('balance_holds');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('deposit_items');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('ledger_accounts');
    }
};
