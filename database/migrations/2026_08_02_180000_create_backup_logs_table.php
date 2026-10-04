<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('backup_pair_uuid')->unique();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('menunggu');
            $table->string('database_location_alias', 80);
            $table->string('media_location_alias', 80);
            $table->char('database_sha256', 64);
            $table->char('media_sha256', 64);
            $table->unsignedBigInteger('database_size_bytes');
            $table->unsignedBigInteger('media_size_bytes');
            $table->dateTime('retention_until');
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('error_reference', 80)->nullable();
            $table->dateTime('restore_tested_at')->nullable();
            $table->string('restore_verification_result', 20)->nullable();
            $table->string('restore_verification_target_alias', 80)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['retention_until', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
