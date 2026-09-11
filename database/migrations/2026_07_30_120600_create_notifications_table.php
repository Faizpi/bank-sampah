<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 100);
            $table->string('title', 160);
            $table->text('body');
            $table->string('reference', 120);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('dedupe_key', 191)->unique();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at', 'created_at'], 'notifications_recipient_read_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
