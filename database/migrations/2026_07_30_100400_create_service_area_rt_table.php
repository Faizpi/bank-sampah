<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_area_rt', function (Blueprint $table): void {
            $table->foreignId('service_area_id')->constrained('service_areas')->cascadeOnDelete();
            $table->foreignId('rt_id')->constrained('rt')->cascadeOnDelete();
            $table->unique(['service_area_id', 'rt_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_area_rt');
    }
};
