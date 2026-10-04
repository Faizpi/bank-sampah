<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_service_areas')) {
            Schema::create('staff_service_areas', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('staff_profile_user_id');
                $table->foreign('staff_profile_user_id')->references('user_id')->on('staff_profiles')->restrictOnDelete();
                $table->foreignId('service_area_id')->constrained()->restrictOnDelete();
                $table->date('active_from')->nullable();
                $table->date('active_to')->nullable();
                $table->timestamps();
                $table->unique(['staff_profile_user_id', 'service_area_id']);
                $table->index(['service_area_id', 'active_to']);
            });

            if (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER staff_service_area_dates_insert BEFORE INSERT ON staff_service_areas WHEN NEW.active_to IS NOT NULL AND NEW.active_from IS NOT NULL AND NEW.active_to < NEW.active_from BEGIN SELECT RAISE(ABORT, 'staff service area active dates check failed'); END");
                DB::unprepared("CREATE TRIGGER staff_service_area_dates_update BEFORE UPDATE ON staff_service_areas WHEN NEW.active_to IS NOT NULL AND NEW.active_from IS NOT NULL AND NEW.active_to < NEW.active_from BEGIN SELECT RAISE(ABORT, 'staff service area active dates check failed'); END");
            } else {
                DB::statement('ALTER TABLE staff_service_areas ADD CONSTRAINT staff_service_area_active_dates_check CHECK (active_to IS NULL OR active_from IS NULL OR active_to >= active_from)');
            }
        }

        DB::table('staff_profiles')
            ->select(['user_id', 'service_area_id', 'active_from', 'active_to', 'created_at', 'updated_at'])
            ->whereNotNull('service_area_id')
            ->orderBy('user_id')
            ->eachById(function (object $profile): void {
                DB::table('staff_service_areas')->updateOrInsert(
                    [
                        'staff_profile_user_id' => $profile->user_id,
                        'service_area_id' => $profile->service_area_id,
                    ],
                    [
                        'active_from' => $profile->active_from,
                        'active_to' => $profile->active_to,
                        'created_at' => $profile->created_at ?? now(),
                        'updated_at' => $profile->updated_at ?? now(),
                    ],
                );
            }, 1000, 'user_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_service_areas');
    }
};
