<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->string('customer_number', 40)->nullable()->unique();
            $table->foreignId('rt_id')->index()->constrained('rt')->restrictOnDelete();
            $table->string('address', 500);
            $table->date('joined_at')->nullable()->index();
            $table->char('qr_token_hash', 64)->nullable()->unique();
            $table->timestamp('qr_rotated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
