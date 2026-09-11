<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{columns: list<string>, unique: string}> */
    private const PIVOT_KEYS = [
        'permission_role' => [
            'columns' => ['permission_id', 'role_id'],
            'unique' => 'permission_role_permission_id_role_id_unique',
        ],
        'role_user' => [
            'columns' => ['role_id', 'user_id'],
            'unique' => 'role_user_role_id_user_id_unique',
        ],
        'service_area_rt' => [
            'columns' => ['service_area_id', 'rt_id'],
            'unique' => 'service_area_rt_service_area_id_rt_id_unique',
        ],
        'waste_type_conditions' => [
            'columns' => ['waste_type_id', 'waste_condition_id'],
            'unique' => 'waste_type_conditions_waste_type_id_waste_condition_id_unique',
        ],
    ];

    public function up(): void
    {
        foreach (self::PIVOT_KEYS as $tableName => $key) {
            $this->promoteUniquePairToPrimaryKey($tableName, $key['columns'], $key['unique']);
        }

        Schema::table('transaction_corrections', function (Blueprint $table): void {
            $table->unique('deposit_id', 'transaction_corrections_deposit_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_corrections', function (Blueprint $table): void {
            $table->dropUnique('transaction_corrections_deposit_id_unique');
        });

        foreach (self::PIVOT_KEYS as $tableName => $key) {
            $this->restoreUniquePair($tableName, $key['columns'], $key['unique']);
        }
    }

    /** @param list<string> $columns */
    private function promoteUniquePairToPrimaryKey(string $tableName, array $columns, string $uniqueKey): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($columns, $uniqueKey): void {
            $table->primary($columns);
            $table->dropUnique($uniqueKey);
        });
    }

    /** @param list<string> $columns */
    private function restoreUniquePair(string $tableName, array $columns, string $uniqueKey): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($columns, $tableName, $uniqueKey): void {
            $table->unique($columns, $uniqueKey);
            $table->dropPrimary($this->primaryKeyName($tableName, $columns));
        });
    }

    /** @param list<string> $columns */
    private function primaryKeyName(string $tableName, array $columns): string
    {
        return $tableName.'_'.implode('_', $columns).'_primary';
    }
};
