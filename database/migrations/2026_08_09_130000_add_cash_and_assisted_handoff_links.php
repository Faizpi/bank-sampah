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
        Schema::table('reconciliations', function (Blueprint $table): void {
            $table->unsignedBigInteger('cash_total')->nullable()->after('hold_total');
        });

        Schema::table('assisted_customer_services', function (Blueprint $table): void {
            $table->foreignId('deposit_id')->nullable()->unique()->after('evidence_media_id')->constrained('deposits')->restrictOnDelete();
        });

        $this->restoreAssistedCustomerServiceTriggers();
    }

    public function down(): void
    {
        Schema::table('assisted_customer_services', function (Blueprint $table): void {
            $table->dropForeign(['deposit_id']);
            $table->dropUnique(['deposit_id']);
            $table->dropColumn('deposit_id');
        });

        $this->restoreAssistedCustomerServiceTriggers();

        Schema::table('reconciliations', function (Blueprint $table): void {
            $table->dropColumn('cash_total');
        });
    }

    private function restoreAssistedCustomerServiceTriggers(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS assisted_customer_services_owner_operator_insert');
        DB::unprepared('DROP TRIGGER IF EXISTS assisted_customer_services_owner_operator_update');
        DB::unprepared("CREATE TRIGGER assisted_customer_services_owner_operator_insert BEFORE INSERT ON assisted_customer_services WHEN NEW.owner_id = NEW.operator_id BEGIN SELECT RAISE(ABORT, 'assisted service owner and operator must differ'); END");
        DB::unprepared("CREATE TRIGGER assisted_customer_services_owner_operator_update BEFORE UPDATE OF owner_id, operator_id ON assisted_customer_services WHEN NEW.owner_id = NEW.operator_id BEGIN SELECT RAISE(ABORT, 'assisted service owner and operator must differ'); END");
    }
};
