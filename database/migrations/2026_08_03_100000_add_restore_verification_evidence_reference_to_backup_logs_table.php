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
            $table->string('restore_verification_evidence_reference', 80)->nullable()->after('restore_verification_target_alias');
        });
    }

    public function down(): void
    {
        Schema::table('backup_logs', function (Blueprint $table): void {
            $table->dropColumn('restore_verification_evidence_reference');
        });
    }
};
