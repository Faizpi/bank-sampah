<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('report_type', 40);
            $table->json('filters');
            $table->json('columns')->nullable();
            $table->string('format', 12);
            $table->string('sort', 40)->default('occurred_at');
            $table->string('direction', 4)->default('desc');
            $table->string('status', 20)->default('menunggu');
            $table->string('disk', 64)->nullable();
            $table->string('path', 1024)->nullable();
            $table->string('filename', 255)->nullable();
            $table->foreignId('media_id')->nullable()->unique()->constrained('media')->restrictOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('completed_at')->nullable();
            $table->string('error_reference', 80)->nullable();
            $table->timestamps();
            $table->index(['requester_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->date('business_date');
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->restrictOnDelete();
            $table->string('scope_key', 80);
            $table->string('status', 24)->default('draf');
            $table->unsignedInteger('version');
            $table->foreignId('parent_id')->nullable()->constrained('reconciliations')->restrictOnDelete();
            $table->unsignedBigInteger('opening_total')->default(0);
            $table->unsignedBigInteger('deposit_total')->default(0);
            $table->unsignedBigInteger('withdrawal_total')->default(0);
            $table->unsignedBigInteger('grocery_total')->default(0);
            $table->unsignedBigInteger('hold_total')->default(0);
            $table->unsignedBigInteger('closing_total')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejector_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->timestamps();
            $table->unique(['business_date', 'scope_key', 'version'], 'reconciliations_date_scope_version_unique');
            $table->index(['service_area_id', 'business_date', 'status']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reconciliation_id')->constrained('reconciliations')->restrictOnDelete();
            $table->string('item_type', 40);
            $table->string('reference_type', 160)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('expected_total')->default(0);
            $table->unsignedBigInteger('actual_total')->default(0);
            $table->bigInteger('difference')->default(0);
            $table->string('status', 20)->default('terbuka');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['reconciliation_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('reconciliations');
        Schema::dropIfExists('report_exports');
    }
};
