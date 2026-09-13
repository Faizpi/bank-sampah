<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use JsonException;
use LogicException;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

final class MobileServicesRetirementMigrationTest extends TestCase
{
    private const MIGRATION = '2026_09_12_230000_retire_mobile_services.php';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'retirement_test');
        config()->set('database.connections.retirement_test', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('retirement_test');
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
    }

    protected function tearDown(): void
    {
        DB::purge('retirement_test');
        parent::tearDown();
    }

    public function test_fresh_install_retires_mobile_services_and_refuses_rollback(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertRetired();
        $this->retirement()->up();
        $this->assertRetired();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Restore a verified pre-migration backup');
        $this->retirement()->down();
    }

    public function test_populated_upgrade_preserves_finances_and_unrelated_rows(): void
    {
        $paths = glob(database_path('migrations/*.php'));
        self::assertIsArray($paths);
        $paths = array_values(array_filter($paths, static fn (string $path): bool => basename($path) !== self::MIGRATION));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        self::assertTrue(Schema::hasIndex('deposits', 'deposits_mobile_service_id_status_index'));
        self::assertContains(['mobile_service_id'], array_column(Schema::getForeignKeys('deposits'), 'columns'));
        $this->seedUpgrade();

        $preserved = [];
        $checkedOrRetired = ['migrations', 'deposits', 'statistic_publications', 'mobile_services', 'mobile_service_staff', 'mobile_service_waste_types'];
        foreach (Schema::getTableListing() as $listed) {
            $table = str_contains($listed, '.') ? substr($listed, strrpos($listed, '.') + 1) : $listed;
            if (in_array($table, $checkedOrRetired, true)) {
                continue;
            }
            $preserved[$table] = DB::table($table)->get()->toJson();
        }
        $deposits = DB::table('deposits')->orderBy('id')->get()->map(static function (object $row): array {
            $values = (array) $row;
            unset($values['mobile_service_id']);
            if ($values['method'] === 'keliling') {
                $values['method'] = 'langsung';
            }

            return $values;
        })->all();
        $triggers = $this->financialTriggerSql();
        self::assertCount(8, $triggers);
        $publication = (array) DB::table('statistic_publications')->where('id', 1)->first();
        unset($publication['metrics']);

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertRetired();
        self::assertSame($deposits, DB::table('deposits')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all());
        foreach ($preserved as $table => $rows) {
            self::assertSame($rows, DB::table($table)->get()->toJson(), $table);
        }
        self::assertEquals($triggers, $this->financialTriggerSql());
        self::assertSame([], DB::select('PRAGMA foreign_key_check'));
        self::assertSame(1, (int) DB::scalar('PRAGMA foreign_keys'));
        self::assertEquals(5000, DB::table('ledger_entries')->sum('amount'));
        self::assertEquals(1000, DB::table('balance_holds')->sum('amount'));
        $after = (array) DB::table('statistic_publications')->where('id', 1)->first();
        self::assertSame(['deposit_count', 'total_weight_kg'], json_decode($after['metrics'], true, 512, JSON_THROW_ON_ERROR));
        unset($after['metrics']);
        self::assertSame($publication, $after);
        self::assertSame('[]', DB::table('statistic_publications')->where('id', 2)->value('metrics'));
        self::assertSame('[ "deposit_count", "total_weight_kg" ]', DB::table('statistic_publications')->where('id', 3)->value('metrics'));
        self::assertSame('[]', DB::table('statistic_publications')->where('id', 4)->value('metrics'));

        $this->retirement()->up();
        $this->assertRetired();
        self::assertSame($deposits, DB::table('deposits')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all());
    }

    private function assertRetired(): void
    {
        foreach (['mobile_service_waste_types', 'mobile_service_staff', 'mobile_services'] as $table) {
            self::assertFalse(Schema::hasTable($table));
        }
        self::assertFalse(Schema::hasColumn('deposits', 'mobile_service_id'));
        self::assertFalse(Schema::hasIndex('deposits', 'deposits_mobile_service_id_status_index'));
        self::assertNotContains(['mobile_service_id'], array_column(Schema::getForeignKeys('deposits'), 'columns'));
        self::assertTrue(Schema::hasTable('statistic_publications'));
        foreach (DB::table('statistic_publications')->pluck('metrics') as $metrics) {
            try {
                $decoded = json_decode($metrics, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
            if (! is_array($decoded)) {
                continue;
            }
            // metrics is a list<string> of metric names; the retired statistic must not
            // survive as an element. assertNotContains works for both lists and keyed arrays.
            self::assertNotContains('mobile_service_count', $decoded);
        }
    }

    private function retirement(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }

    /** @return list<object> */
    private function financialTriggerSql(): array
    {
        return DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND (name LIKE 'ledger_entries_prevent_%' OR name LIKE 'balance_holds_prevent_%' OR name LIKE 'transaction_corrections_prevent_%' OR name LIKE 'transaction_reversals_prevent_%') ORDER BY name",
        );
    }

    private function seedUpgrade(): void
    {
        $time = '2026-09-01 10:00:00';
        DB::table('users')->insert(['id' => 1, 'name' => 'Migration fixture', 'email' => 'migration@example.test', 'password' => 'unused']);
        DB::table('waste_categories')->insert(['id' => 1, 'code' => 'P', 'name' => 'Paper']);
        DB::table('waste_units')->insert(['id' => 1, 'code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'classification' => 'berat']);
        DB::table('waste_conditions')->insert(['id' => 1, 'code' => 'C', 'name' => 'Clean']);
        DB::table('waste_types')->insert(['id' => 1, 'waste_category_id' => 1, 'waste_unit_id' => 1, 'code' => 'PAPER', 'name' => 'Paper']);
        DB::table('mobile_services')->insert([
            'id' => 1, 'service_number' => 'MS-1', 'point' => 'Old location', 'starts_at' => $time,
            'ends_at' => '2026-09-01 12:00:00', 'capacity' => 10, 'served_count' => 1, 'created_by' => 1,
        ]);
        DB::table('mobile_service_staff')->insert(['mobile_service_id' => 1, 'staff_id' => 1]);
        DB::table('mobile_service_waste_types')->insert(['mobile_service_id' => 1, 'waste_type_id' => 1]);
        foreach (['keliling', 'langsung', 'penjemputan'] as $index => $method) {
            DB::table('deposits')->insert([
                'id' => $index + 1, 'deposit_number' => 'DEP-'.$index, 'customer_id' => 1, 'staff_id' => 1,
                'method' => $method, 'mobile_service_id' => $index === 0 ? 1 : null, 'occurred_at' => $time,
                'status' => 'final', 'total_weight_kg' => 2, 'total_value' => 5000, 'finalized_at' => $time,
                'idempotency_key' => 'deposit-'.$index, 'created_at' => $time, 'updated_at' => $time,
            ]);
        }
        DB::table('deposit_items')->insert([
            'deposit_id' => 1, 'waste_type_id' => 1, 'waste_condition_id' => 1,
            'weight_kg' => 2, 'price_per_unit' => 2500, 'subtotal' => 5000, 'price_snapshot' => '{"price":2500}',
        ]);
        DB::table('ledger_accounts')->insert(['id' => 1, 'user_id' => 1]);
        DB::table('ledger_entries')->insert([
            'id' => 1, 'entry_number' => 'LE-1', 'ledger_account_id' => 1, 'direction' => 'in', 'kind' => 'deposit',
            'amount' => 5000, 'source_type' => 'deposit', 'source_id' => 1, 'source_key' => 'deposit-1',
            'effective_at' => $time, 'balance_after' => 5000,
        ]);
        DB::table('balance_holds')->insert([
            'hold_number' => 'BH-1', 'ledger_account_id' => 1, 'source_type' => 'withdrawal', 'source_id' => 1,
            'source_key' => 'hold-1', 'amount' => 1000, 'held_at' => $time,
        ]);
        DB::table('transaction_corrections')->insert([
            'correction_number' => 'COR-1', 'deposit_id' => 1, 'reason' => 'Historical correction',
            'before_values' => '{}', 'after_values' => '{}', 'delta_value' => 0, 'created_by' => 1, 'finalized_at' => $time,
        ]);
        DB::table('transaction_reversals')->insert([
            'reversal_number' => 'REV-1', 'original_deposit_id' => 2, 'original_entry_id' => 1,
            'reason' => 'Historical reversal', 'created_by' => 1, 'finalized_at' => $time,
        ]);
        DB::table('idempotency_keys')->insert([
            'actor_id' => 1, 'scope' => 'deposit', 'key' => 'deposit-1', 'payload_hash' => str_repeat('a', 64),
        ]);
        foreach ([
            '["deposit_count","mobile_service_count","total_weight_kg"]',
            '["mobile_service_count"]',
            '[ "deposit_count", "total_weight_kg" ]',
            '[]',
        ] as $index => $metrics) {
            DB::table('statistic_publications')->insert([
                'id' => $index + 1, 'publication_key' => 'snapshot-'.$index, 'metrics' => $metrics,
                'dimensions' => '{"period":"2026-09"}', 'approved_by' => 1, 'approved_at' => $time,
                'created_at' => $time, 'updated_at' => $time,
            ]);
        }
    }

    public function test_metric_retirement_uses_real_list_shape_and_skips_unexpected_shapes(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $time = '2026-09-01 10:00:00';
        $rows = [
            // Real app-produced shape (StatisticsService::configurePublic) plus a retired element.
            5 => json_encode(['active_customers', 'mobile_service_count', 'total_weight_kg', 'mobile_service_count'], JSON_THROW_ON_ERROR),
            // Retired metric absent: row must stay byte-for-byte identical.
            6 => json_encode(['deposit_count', 'plastic_weight_kg'], JSON_THROW_ON_ERROR),
            // Already empty list.
            7 => '[]',
            // Malformed JSON: preserved untouched and logged.
            8 => '{"mobile_service_count":',
            // Object shape the app never writes: tolerated, skipped and logged, never nulled.
            110 => '{"deposit_count":5,"mobile_service_count":1}',
            // Mixed list containing a non-string element: also an unexpected shape, skipped and logged.
            111 => '["deposit_count",9007199254740993]',
        ];
        foreach ($rows as $id => $metrics) {
            DB::table('statistic_publications')->insert([
                'id' => $id, 'publication_key' => 'shape-'.$id, 'metrics' => $metrics,
                'dimensions' => '["period"]', 'approved_by' => null, 'approved_at' => null,
                'created_at' => $time, 'updated_at' => $time,
            ]);
        }

        for ($id = 9; $id <= 109; $id++) {
            DB::table('statistic_publications')->insert([
                'id' => $id, 'publication_key' => 'chunk-'.$id,
                'metrics' => '["mobile_service_count","deposit_count","mobile_service_count"]',
                'dimensions' => '["period"]',
            ]);
        }

        $log = Mockery::spy(LoggerInterface::class);
        Log::swap($log);

        $this->retirement()->up();

        $log->shouldHaveReceived('warning')
            ->with('Mobile statistic retirement skipped malformed metrics JSON.', Mockery::on(static fn (array $context): bool => ($context['statistic_publication_id'] ?? null) === 8))
            ->once();
        $log->shouldHaveReceived('warning')
            ->with('Mobile statistic retirement skipped unexpected metrics shape.', Mockery::on(static fn (array $context): bool => ($context['statistic_publication_id'] ?? null) === 110))
            ->once();
        $log->shouldHaveReceived('warning')
            ->with('Mobile statistic retirement skipped unexpected metrics shape.', Mockery::on(static fn (array $context): bool => ($context['statistic_publication_id'] ?? null) === 111))
            ->once();

        foreach (DB::table('statistic_publications')->whereBetween('id', [9, 109])->pluck('metrics') as $metrics) {
            self::assertSame('["deposit_count"]', $metrics);
        }

        // All occurrences of the retired element are gone; remaining order is preserved.
        self::assertSame(
            json_encode(['active_customers', 'total_weight_kg'], JSON_THROW_ON_ERROR),
            DB::table('statistic_publications')->where('id', 5)->value('metrics'),
        );
        // Untouched rows keep their exact original encoding.
        self::assertSame(json_encode(['deposit_count', 'plastic_weight_kg'], JSON_THROW_ON_ERROR), DB::table('statistic_publications')->where('id', 6)->value('metrics'));
        self::assertSame('[]', DB::table('statistic_publications')->where('id', 7)->value('metrics'));
        self::assertSame('{"mobile_service_count":', DB::table('statistic_publications')->where('id', 8)->value('metrics'));
        // Unexpected shapes are skipped safely (logged, not rewritten, never nulled).
        self::assertSame('{"deposit_count":5,"mobile_service_count":1}', DB::table('statistic_publications')->where('id', 110)->value('metrics'));
        self::assertSame('["deposit_count",9007199254740993]', DB::table('statistic_publications')->where('id', 111)->value('metrics'));
    }
}
