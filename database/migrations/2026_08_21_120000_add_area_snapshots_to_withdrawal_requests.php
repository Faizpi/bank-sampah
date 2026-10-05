<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->foreignId('rt_id')->nullable()->after('customer_id')->constrained('rt')->restrictOnDelete();
            $table->foreignId('service_area_id')->nullable()->after('rt_id')->constrained('service_areas')->restrictOnDelete();
            $table->index(['service_area_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_requests', function (Blueprint $table): void {
            $table->dropIndex(['service_area_id', 'status']);
            $table->dropConstrainedForeignId('service_area_id');
            $table->dropConstrainedForeignId('rt_id');
        });
    }
};
