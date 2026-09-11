<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class SchemaIntegrityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_documented_pivot_pairs_are_composite_primary_keys(): void
    {
        foreach ([
            'permission_role' => ['permission_id', 'role_id'],
            'role_user' => ['role_id', 'user_id'],
            'service_area_rt' => ['service_area_id', 'rt_id'],
            'waste_type_conditions' => ['waste_type_id', 'waste_condition_id'],
        ] as $table => $columns) {
            self::assertTrue(
                collect(Schema::getIndexes($table))->contains(
                    static fn (array $index): bool => $index['primary'] && $index['columns'] === $columns,
                ),
                "{$table} must use its relationship pair as the primary key.",
            );
        }
    }

    public function test_a_deposit_can_have_at_most_one_transaction_correction(): void
    {
        self::assertTrue(
            collect(Schema::getIndexes('transaction_corrections'))->contains(
                static fn (array $index): bool => $index['unique'] && $index['columns'] === ['deposit_id'],
            ),
            'transaction_corrections.deposit_id must be unique.',
        );
    }
}
