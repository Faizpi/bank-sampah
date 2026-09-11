<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->foreignId('mobile_service_id')->nullable()->after('pickup_request_id')->constrained('mobile_services')->restrictOnDelete();
            $table->index(['mobile_service_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropForeign(['mobile_service_id']);
            $table->dropIndex(['mobile_service_id', 'status']);
            $table->dropColumn('mobile_service_id');
        });
    }
};
