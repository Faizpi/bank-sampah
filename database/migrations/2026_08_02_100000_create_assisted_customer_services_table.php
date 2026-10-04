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
        Schema::create('assisted_customer_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $table->string('service_type', 64);
            $table->string('consent_version', 64);
            $table->timestamp('consented_at');
            $table->foreignId('evidence_media_id')->constrained('media')->restrictOnDelete();
            $table->timestamps();
            $table->index(['owner_id', 'created_at']);
            $table->index(['operator_id', 'created_at']);
            $table->unique(['owner_id', 'operator_id', 'service_type', 'consented_at'], 'assisted_customer_services_deduplication');
        });

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER assisted_customer_services_owner_operator_insert BEFORE INSERT ON assisted_customer_services WHEN NEW.owner_id = NEW.operator_id BEGIN SELECT RAISE(ABORT, 'assisted service owner and operator must differ'); END");
            DB::unprepared("CREATE TRIGGER assisted_customer_services_owner_operator_update BEFORE UPDATE OF owner_id, operator_id ON assisted_customer_services WHEN NEW.owner_id = NEW.operator_id BEGIN SELECT RAISE(ABORT, 'assisted service owner and operator must differ'); END");
        } else {
            DB::statement('ALTER TABLE assisted_customer_services ADD CONSTRAINT assisted_customer_services_owner_operator_check CHECK (owner_id <> operator_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assisted_customer_services');
    }
};
