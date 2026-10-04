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
            $table->dateTime('review_requested_at')->nullable()->after('finalized_at');
            $table->foreignId('reviewed_by')->nullable()->after('review_requested_at')->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_reason')->nullable()->after('reviewed_at');
            $table->index(['status', 'review_requested_at'], 'deposits_review_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropIndex('deposits_review_queue_index');
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['review_requested_at', 'reviewed_by', 'reviewed_at', 'review_reason']);
        });
    }
};
