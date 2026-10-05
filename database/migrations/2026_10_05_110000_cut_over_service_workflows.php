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
        foreach (['deposits', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions', 'assisted_customer_services'] as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException('Workflow cutover requires an empty baseline. Use the guarded LOCAL demo reset; never discard real transactions.');
            }
        }
        Schema::drop('assisted_customer_services');
        Schema::table('deposits', fn (Blueprint $table) => $table->string('finalization_payload_hash', 64)->nullable());
        foreach (['withdrawal_requests', 'grocery_redemptions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['approver_id']);
                $table->dropIndex(['status', 'expires_at']);
                $table->dropColumn(['approver_id', 'approved_at', 'expires_at', 'recipient_verification', 'recipient_reference']);
                $table->timestamp('claimed_at')->nullable();
                $table->timestamp('cancellation_requested_at')->nullable();
                $table->timestamp('physical_handed_over_at')->nullable();
                $table->text('cancellation_request_reason')->nullable();
                $table->timestamp('claim_released_at')->nullable();
                $table->timestamp('exception_reported_at')->nullable();
                $table->string('exception_kind', 32)->nullable();
                $table->text('exception_reason')->nullable();
                $table->foreignId('exception_resolved_by_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('exception_resolved_at')->nullable();
                $table->text('exception_resolution')->nullable();
                $table->string('settlement_payload_hash', 64)->nullable();
                $table->index(['status', 'created_at']);
            });
        }
        Schema::table('grocery_redemptions', function (Blueprint $table): void {
            $table->dropForeign(['prepared_by_id']);
            $table->dropColumn(['prepared_by_id', 'prepared_at']);
            $table->foreignId('claimed_by_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('pickup_requests', function (Blueprint $table): void {
            $table->string('completion_payload_hash', 64)->nullable();
            $table->dropColumn('accepted_at');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('claim_released_at')->nullable();
            $table->timestamp('exception_reported_at')->nullable();
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_resolved_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('exception_resolved_at')->nullable();
            $table->text('exception_resolution')->nullable();
        });
        foreach ([
            'withdrawal_requests' => ['menunggu_bendahara', 'diproses_bendahara', 'sudah_dibayar', 'dibatalkan'],
            'grocery_redemptions' => ['menunggu_petugas', 'diproses_petugas', 'siap_diambil', 'selesai', 'tidak_tersedia', 'dibatalkan'],
            'pickup_requests' => ['menunggu_petugas', 'dijadwalkan', 'menuju_lokasi', 'dijemput', 'selesai', 'ditolak', 'dibatalkan'],
        ] as $tableName => $statuses) {
            Schema::table($tableName, fn (Blueprint $table) => $table->string('status', 40)->default($statuses[0])->change());
            $values = implode(',', array_map(fn (string $status): string => "'{$status}'", $statuses));
            if (DB::getDriverName() === 'sqlite') {
                foreach (['INSERT', 'UPDATE'] as $event) {
                    DB::unprepared("CREATE TRIGGER {$tableName}_status_{$event} BEFORE {$event} ON {$tableName} WHEN NEW.status NOT IN ({$values}) BEGIN SELECT RAISE(ABORT, 'invalid workflow status'); END");
                }
            } else {
                DB::statement("ALTER TABLE {$tableName} ADD CONSTRAINT {$tableName}_status_check CHECK (CAST(status AS BINARY) IN ({$values}))");
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the pre-cutover code and database checkpoint together.');
    }
};
