<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
            $table->foreignId('waste_condition_id')->constrained('waste_conditions')->restrictOnDelete();
            $table->unsignedBigInteger('price');
            $table->dateTime('effective_from');
            $table->dateTime('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rounding_version', 30)->default('half_up_v1');
            $table->timestamps();
            $table->index(['waste_type_id', 'waste_condition_id', 'effective_from', 'effective_to'], 'waste_prices_resolution_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_prices');
    }
};
