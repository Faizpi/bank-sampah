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
        Schema::create('staff_profiles', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->restrictOnDelete();
            $table->string('staff_number', 40)->unique();
            $table->foreignId('service_area_id')->nullable()->constrained('service_areas')->restrictOnDelete();
            $table->date('active_from')->nullable();
            $table->date('active_to')->nullable();
            $table->timestamps();
            $table->index(['service_area_id', 'active_to']);
        });
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER staff_active_dates_insert BEFORE INSERT ON staff_profiles WHEN NEW.active_to IS NOT NULL AND NEW.active_from IS NOT NULL AND NEW.active_to < NEW.active_from BEGIN SELECT RAISE(ABORT, 'staff active dates check failed'); END");
            DB::unprepared("CREATE TRIGGER staff_active_dates_update BEFORE UPDATE ON staff_profiles WHEN NEW.active_to IS NOT NULL AND NEW.active_from IS NOT NULL AND NEW.active_to < NEW.active_from BEGIN SELECT RAISE(ABORT, 'staff active dates check failed'); END");
        } else {
            DB::statement('ALTER TABLE staff_profiles ADD CONSTRAINT staff_active_dates_check CHECK (active_to IS NULL OR active_from IS NULL OR active_to >= active_from)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
    }
};
