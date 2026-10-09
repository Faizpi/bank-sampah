<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate public catalogue photography from immutable/private transaction evidence.
        Schema::create('waste_type_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waste_type_id')->unique()->constrained('waste_types')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('path', 100)->unique();
            $table->unsignedInteger('size');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_type_photos');
    }
};
