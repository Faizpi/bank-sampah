<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rw', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dusun_id')->constrained('dusun')->restrictOnDelete();
            $table->string('code', 30);
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->unique(['dusun_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rw');
    }
};
