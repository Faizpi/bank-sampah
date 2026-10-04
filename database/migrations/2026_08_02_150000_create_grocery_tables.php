<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grocery_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->text('contents');
            $table->unsignedBigInteger('value');
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();
            $table->string('status', 30)->default('aktif');
            $table->foreignId('media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'active_from', 'active_until']);
        });

        Schema::create('grocery_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('grocery_package_id')->constrained('grocery_packages')->restrictOnDelete();
            $table->unsignedBigInteger('value_snapshot');
            $table->json('package_snapshot');
            $table->string('source_type', 30)->default('saldo');
            $table->string('status', 40)->default('menunggu_verifikasi');
            $table->foreignId('balance_hold_id')->nullable()->unique()->constrained('balance_holds')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('handover_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('proof_media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->foreignId('receipt_ledger_entry_id')->nullable()->unique()->constrained('ledger_entries')->restrictOnDelete();
            $table->text('availability_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('prepared_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('handed_over_at')->nullable();
            $table->string('recipient_verification', 40)->nullable();
            $table->string('recipient_reference', 120)->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['handover_actor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grocery_redemptions');
        Schema::dropIfExists('grocery_packages');
    }
};
