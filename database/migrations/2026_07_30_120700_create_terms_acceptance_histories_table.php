<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms_acceptance_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('accepted_version', 40);
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'accepted_version'], 'terms_acceptance_histories_user_version_unique');
            $table->index(['user_id', 'accepted_at'], 'terms_acceptance_histories_user_accepted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms_acceptance_histories');
    }
};
