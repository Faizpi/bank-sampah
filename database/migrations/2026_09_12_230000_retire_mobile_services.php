<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MySQL/MariaDB DDL commits implicitly. Stop writers and take a verified backup
    // before running: this retirement is not transaction-safe on those engines.
    public $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'method')) {
            DB::table('deposits')->where('method', 'keliling')->update(['method' => 'langsung']);
        }

        $this->retireMobileMetric();

        if (Schema::hasTable('deposits') && Schema::hasColumn('deposits', 'mobile_service_id')) {
            foreach (Schema::getForeignKeys('deposits') as $foreignKey) {
                if ($foreignKey['columns'] === ['mobile_service_id']) {
                    Schema::table('deposits', function (Blueprint $table): void {
                        // Resolves to deposits_mobile_service_id_foreign on MySQL;
                        // SQLite requires columns rather than a constraint name.
                        $table->dropForeign(['mobile_service_id']);
                    });
                    break;
                }
            }

            if (Schema::hasIndex('deposits', 'deposits_mobile_service_id_status_index')) {
                Schema::table('deposits', function (Blueprint $table): void {
                    $table->dropIndex('deposits_mobile_service_id_status_index');
                });
            }

            Schema::table('deposits', function (Blueprint $table): void {
                $table->dropColumn('mobile_service_id');
            });
        }

        Schema::dropIfExists('mobile_service_waste_types');
        Schema::dropIfExists('mobile_service_staff');
        Schema::dropIfExists('mobile_services');
    }

    public function down(): void
    {
        // Deleted schedules, links and metrics cannot be reconstructed; converted
        // methods cannot be distinguished from original langsung rows. Restore a backup.
        throw new LogicException('Mobile services retirement is irreversible. Restore a verified pre-migration backup.');
    }

    private function retireMobileMetric(): void
    {
        if (! Schema::hasTable('statistic_publications') || ! Schema::hasColumn('statistic_publications', 'metrics')) {
            return;
        }

        DB::table('statistic_publications')->select('id', 'metrics')
            ->chunkById(100, function (iterable $rows): void {
                foreach ($rows as $row) {
                    try {
                        $metrics = json_decode($row->metrics, false, 512, JSON_THROW_ON_ERROR);
                    } catch (JsonException $exception) {
                        // Preserve malformed payloads unchanged; operators must repair these rows separately.
                        Log::warning('Mobile statistic retirement skipped malformed metrics JSON.', [
                            'statistic_publication_id' => $row->id,
                            'error' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    // Only the app-produced list<string> shape is eligible for rewriting.
                    if (! is_array($metrics) || array_filter($metrics, static fn (mixed $metric): bool => ! is_string($metric)) !== []) {
                        Log::warning('Mobile statistic retirement skipped unexpected metrics shape.', [
                            'statistic_publication_id' => $row->id,
                        ]);

                        continue;
                    }

                    if (! in_array('mobile_service_count', $metrics, true)) {
                        continue;
                    }

                    $filtered = array_values(array_filter($metrics, static fn (string $metric): bool => $metric !== 'mobile_service_count'));
                    DB::table('statistic_publications')->where('id', $row->id)->update([
                        'metrics' => json_encode($filtered, JSON_THROW_ON_ERROR),
                    ]);
                }
            });
    }
};
