<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index('deleted_at', 'users_deleted_at_index');
        });

        Schema::table('pickup_requests', function (Blueprint $table): void {
            $table->index(['status', 'scheduled_date'], 'pickup_requests_status_scheduled_date_index');
        });

        Schema::table('report_exports', function (Blueprint $table): void {
            $table->index(['expires_at', 'status'], 'report_exports_expires_at_status_index');
        });

        Schema::table('notification_delivery_failures', function (Blueprint $table): void {
            $table->index('last_attempted_at', 'notification_delivery_failures_last_attempted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('notification_delivery_failures', function (Blueprint $table): void {
            $table->dropIndex('notification_delivery_failures_last_attempted_at_index');
        });

        Schema::table('report_exports', function (Blueprint $table): void {
            $table->dropIndex('report_exports_expires_at_status_index');
        });

        Schema::table('pickup_requests', function (Blueprint $table): void {
            $table->dropIndex('pickup_requests_status_scheduled_date_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_deleted_at_index');
        });
    }
};
