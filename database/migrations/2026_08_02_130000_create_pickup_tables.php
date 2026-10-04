<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_capacities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_area_id')->constrained('service_areas')->restrictOnDelete();
            $table->date('service_date');
            $table->unsignedInteger('max_addresses')->nullable();
            $table->decimal('max_weight_kg', 15, 3)->nullable();
            $table->string('vehicle_label', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['service_area_id', 'service_date']);
            $table->index(['service_date', 'is_active']);
        });

        Schema::create('pickup_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 40)->unique();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('rt_id')->constrained('rt')->restrictOnDelete();
            $table->foreignId('service_area_id')->constrained('service_areas')->restrictOnDelete();
            $table->string('address', 500);
            $table->date('selected_date');
            $table->date('scheduled_date')->nullable();
            $table->decimal('estimated_weight_kg', 15, 3)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('menunggu_pemeriksaan');
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->foreignId('assigned_staff_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('deposit_id')->nullable()->unique()->constrained('deposits')->restrictOnDelete();
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('en_route_at')->nullable();
            $table->dateTime('picked_up_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'selected_date']);
            $table->index(['service_area_id', 'selected_date', 'status'], 'pickup_capacity_lookup_index');
            $table->index(['assigned_staff_id', 'status', 'scheduled_date']);
        });

        Schema::table('deposits', function (Blueprint $table): void {
            $table->foreignId('pickup_request_id')->nullable()->unique()->after('method')->constrained('pickup_requests')->restrictOnDelete();
        });

        Schema::create('pickup_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pickup_request_id')->constrained('pickup_requests')->restrictOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
            $table->decimal('estimated_weight_kg', 15, 3)->nullable();
            $table->unsignedInteger('estimated_quantity')->nullable();
            $table->timestamps();
            $table->index(['pickup_request_id', 'waste_type_id']);
        });

        Schema::create('status_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 160);
            $table->unsignedBigInteger('subject_id');
            $table->string('old_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at');
            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'status_history_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_histories');
        Schema::dropIfExists('pickup_items');
        Schema::table('deposits', function (Blueprint $table): void {
            $table->dropForeign(['pickup_request_id']);
            $table->dropUnique(['pickup_request_id']);
            $table->dropColumn('pickup_request_id');
        });
        Schema::dropIfExists('pickup_requests');
        Schema::dropIfExists('pickup_capacities');
    }
};
