<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('delivery_status', 20)->default('delivered')->after('dedupe_key');
            $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('delivery_status');
            $table->dateTime('delivered_at')->nullable()->after('delivery_attempts');
            $table->text('last_delivery_error')->nullable()->after('delivered_at');
        });

        Schema::create('notification_delivery_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error');
            $table->dateTime('last_attempted_at');
            $table->dateTime('retry_after')->nullable();
            $table->timestamps();
            $table->index(['retry_after', 'attempts']);
        });

        Schema::create('announcements', function (Blueprint $table): void {
            $table->id();
            $table->string('announcement_number', 40)->unique();
            $table->string('title', 160);
            $table->text('body');
            $table->string('audience', 30);
            $table->dateTime('publish_start');
            $table->dateTime('publish_end')->nullable();
            $table->string('status', 20)->default('draf');
            $table->unsignedInteger('priority')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'audience', 'publish_start', 'publish_end'], 'announcements_public_window_index');
        });

        Schema::create('announcement_rt', function (Blueprint $table): void {
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('rt_id')->constrained('rt')->restrictOnDelete();
            $table->primary(['announcement_id', 'rt_id']);
        });

        Schema::create('collection_targets', function (Blueprint $table): void {
            $table->id();
            $table->string('target_number', 40)->unique();
            $table->string('name', 160);
            $table->text('purpose');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_weight_kg', 15, 3);
            $table->string('status', 20)->default('draf');
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('public_min_subjects')->default(5);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('closed_progress_kg', 15, 3)->nullable();
            $table->timestamps();
            $table->index(['status', 'period_start', 'period_end'], 'collection_targets_period_index');
        });

        Schema::create('target_scopes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collection_target_id')->constrained('collection_targets')->cascadeOnDelete();
            $table->foreignId('waste_type_id')->nullable()->constrained('waste_types')->restrictOnDelete();
            $table->foreignId('waste_category_id')->nullable()->constrained('waste_categories')->restrictOnDelete();
            $table->foreignId('rt_id')->nullable()->constrained('rt')->restrictOnDelete();
            $table->timestamps();
            $table->index(['collection_target_id', 'waste_type_id', 'waste_category_id', 'rt_id'], 'target_scope_lookup_index');
        });

        Schema::create('mobile_services', function (Blueprint $table): void {
            $table->id();
            $table->string('service_number', 40)->unique();
            $table->foreignId('rw_id')->nullable()->constrained('rw')->restrictOnDelete();
            $table->foreignId('rt_id')->nullable()->constrained('rt')->restrictOnDelete();
            $table->string('point', 255);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('draf');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('served_count')->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['rt_id', 'starts_at', 'ends_at'], 'mobile_services_region_window_index');
            $table->index(['status', 'starts_at']);
        });

        Schema::create('mobile_service_staff', function (Blueprint $table): void {
            $table->foreignId('mobile_service_id')->constrained('mobile_services')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->primary(['mobile_service_id', 'staff_id']);
            $table->index('staff_id');
        });

        Schema::create('mobile_service_waste_types', function (Blueprint $table): void {
            $table->foreignId('mobile_service_id')->constrained('mobile_services')->cascadeOnDelete();
            $table->foreignId('waste_type_id')->constrained('waste_types')->restrictOnDelete();
            $table->primary(['mobile_service_id', 'waste_type_id']);
        });

        Schema::create('statistic_publications', function (Blueprint $table): void {
            $table->id();
            $table->string('publication_key', 80)->unique();
            $table->json('metrics');
            $table->json('dimensions');
            $table->unsignedInteger('privacy_threshold')->default(5);
            $table->boolean('is_active')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistic_publications');
        Schema::dropIfExists('mobile_service_waste_types');
        Schema::dropIfExists('mobile_service_staff');
        Schema::dropIfExists('mobile_services');
        Schema::dropIfExists('target_scopes');
        Schema::dropIfExists('collection_targets');
        Schema::dropIfExists('announcement_rt');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notification_delivery_failures');
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn(['delivery_status', 'delivery_attempts', 'delivered_at', 'last_delivery_error']);
        });
    }
};
