<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('waste_units', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('symbol', 30);
            $table->string('classification', 30);
            $table->decimal('conversion_factor_to_kg', 18, 6)->nullable();
        });

        Schema::create('waste_conditions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('waste_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waste_category_id')->constrained('waste_categories')->restrictOnDelete();
            $table->foreignId('waste_unit_id')->constrained('waste_units')->restrictOnDelete();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->text('education_description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_plastic')->default(false);
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->index(['waste_category_id', 'is_active', 'is_plastic']);
        });

        Schema::create('waste_type_conditions', function (Blueprint $table): void {
            $table->foreignId('waste_type_id')->constrained('waste_types')->cascadeOnDelete();
            $table->foreignId('waste_condition_id')->constrained('waste_conditions')->cascadeOnDelete();
            $table->unique(['waste_type_id', 'waste_condition_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_type_conditions');
        Schema::dropIfExists('waste_types');
        Schema::dropIfExists('waste_conditions');
        Schema::dropIfExists('waste_units');
        Schema::dropIfExists('waste_categories');
    }
};
