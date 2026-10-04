<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

final class RegionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_schema_has_documented_columns_without_automatic_timestamps(): void
    {
        self::assertTrue(Schema::hasColumns('dusun', ['id', 'code', 'name', 'is_active']));
        self::assertTrue(Schema::hasColumns('rw', ['id', 'dusun_id', 'code', 'name', 'is_active']));
        self::assertTrue(Schema::hasColumns('rt', ['id', 'rw_id', 'code', 'name', 'is_active']));
        self::assertTrue(Schema::hasColumns('service_areas', ['id', 'name', 'is_active']));
        self::assertTrue(Schema::hasColumns('service_area_rt', ['service_area_id', 'rt_id']));

        foreach (['dusun', 'rw', 'rt', 'service_areas', 'service_area_rt'] as $table) {
            self::assertFalse(Schema::hasColumn($table, 'created_at'));
            self::assertFalse(Schema::hasColumn($table, 'updated_at'));
        }
    }

    public function test_region_schema_has_active_lookup_and_unique_scope_indexes(): void
    {
        self::assertContains(['code'], $this->uniqueIndexes('dusun'));
        self::assertContains(['dusun_id', 'code'], $this->uniqueIndexes('rw'));
        self::assertContains(['rw_id', 'code'], $this->uniqueIndexes('rt'));
        self::assertContains(['name'], $this->uniqueIndexes('service_areas'));
        self::assertContains(['service_area_id', 'rt_id'], $this->uniqueIndexes('service_area_rt'));

        foreach (['dusun', 'rw', 'rt', 'service_areas'] as $table) {
            self::assertContains(['is_active'], $this->indexes($table));
        }
    }

    public function test_scoped_codes_and_pivot_pairs_reject_duplicates(): void
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $otherDusun = Dusun::query()->create(['code' => 'DS-02', 'name' => 'Dusun Dua']);
        Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        Rw::query()->create(['dusun_id' => $otherDusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);

        $this->expectException(QueryException::class);
        Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'Duplikat']);
    }

    public function test_foreign_keys_reject_invalid_parents_and_models_restrict_administrative_deletion(): void
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);

        self::assertDatabaseHas('dusun', ['id' => $dusun->id]);
        $this->expectException(LogicException::class);
        $dusun->delete();
    }

    public function test_service_area_records_are_not_physically_deleted(): void
    {
        [, $area] = $this->regionWithArea();

        $this->expectException(LogicException::class);
        $area->delete();
    }

    public function test_models_expose_typed_hierarchy_and_service_area_relationships_with_boolean_casts(): void
    {
        [$rt, $area, $rw, $dusun] = $this->regionWithArea();
        RegionMutationGuard::run(fn () => $area->rts()->attach($rt));

        self::assertTrue($dusun->refresh()->is_active);
        self::assertTrue($rw->refresh()->is_active);
        self::assertTrue($rt->refresh()->is_active);
        self::assertTrue($area->refresh()->is_active);
        self::assertTrue($dusun->rws->contains($rw));
        self::assertTrue($rw->dusun->is($dusun));
        self::assertTrue($rw->rts->contains($rt));
        self::assertTrue($rt->rw->is($rw));
        self::assertTrue($rt->serviceAreas->contains($area));
        self::assertTrue($area->rts->contains($rt));
    }

    /** @return array{Rt, ServiceArea, Rw, Dusun} */
    private function regionWithArea(): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-01', 'name' => 'Dusun Satu']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-01', 'name' => 'RW Satu']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-01', 'name' => 'RT Satu']);
        $area = ServiceArea::query()->create(['name' => 'Area Utama']);

        return [$rt, $area, $rw, $dusun];
    }

    /** @return list<list<string>> */
    private function indexes(string $table): array
    {
        return array_values(array_map(
            fn (object $index): array => array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name'),
            DB::select("PRAGMA index_list('{$table}')"),
        ));
    }

    /** @return list<list<string>> */
    private function uniqueIndexes(string $table): array
    {
        return array_values(array_map(
            fn (object $index): array => array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name'),
            array_filter(DB::select("PRAGMA index_list('{$table}')"), static fn (object $index): bool => (int) $index->unique === 1),
        ));
    }
}
