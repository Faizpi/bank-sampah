<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('status', 40)->default('menunggu_verifikasi');
            $table->foreignId('balance_hold_id')->nullable()->unique()->constrained('balance_holds')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('payer_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('pickup_location', 255)->nullable();
            $table->date('pickup_date')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('recipient_verification', 40)->nullable();
            $table->string('recipient_reference', 120)->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('proof_media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->foreignId('receipt_ledger_entry_id')->nullable()->unique()->constrained('ledger_entries')->restrictOnDelete();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'expires_at']);
            $table->index(['payer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
