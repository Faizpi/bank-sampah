<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backup_logs', function (Blueprint $table): void {
            $table->string('operator_key', 191)->nullable()->after('initiated_by');
            $table->char('request_payload_hash', 64)->nullable()->after('operator_key');
            $table->unique(['initiated_by', 'operator_key'], 'backup_logs_initiated_by_operator_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('backup_logs', function (Blueprint $table): void {
            $table->dropUnique('backup_logs_initiated_by_operator_key_unique');
            $table->dropColumn(['operator_key', 'request_payload_hash']);
        });
    }
};
