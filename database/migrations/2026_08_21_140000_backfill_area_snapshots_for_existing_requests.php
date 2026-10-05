<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('withdrawal_requests') || ! Schema::hasTable('grocery_redemptions')) {
            return;
        }

        $this->backfill('withdrawal_requests');
        $this->backfill('grocery_redemptions');
    }

    public function down(): void
    {
        // Historical snapshots are immutable once safely resolved.
    }

    private function backfill(string $table): void
    {
        DB::table($table)
            ->select(['id', 'customer_id'])
            ->whereNull('rt_id')
            ->whereNull('service_area_id')
            ->orderBy('id')
            ->eachById(function (object $record) use ($table): void {
                $rtId = DB::table('customer_profiles')->where('user_id', $record->customer_id)->value('rt_id');

                if (! is_int($rtId) && ! ctype_digit((string) $rtId)) {
                    return;
                }

                $areaIds = DB::table('service_area_rt')
                    ->join('service_areas', 'service_areas.id', '=', 'service_area_rt.service_area_id')
                    ->where('service_area_rt.rt_id', $rtId)
                    ->where('service_areas.is_active', true)
                    ->orderBy('service_areas.id')
                    ->pluck('service_areas.id');

                if ($areaIds->count() !== 1) {
                    return;
                }

                DB::table($table)
                    ->where('id', $record->id)
                    ->whereNull('rt_id')
                    ->whereNull('service_area_id')
                    ->update(['rt_id' => $rtId, 'service_area_id' => $areaIds->first()]);
            }, 500, 'id');
    }
};
