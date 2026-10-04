<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Domain\Module;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ModuleBoundaryTest extends TestCase
{
    public function test_modular_monolith_declares_all_expected_domain_modules(): void
    {
        self::assertSame([
            'IdentityAccess',
            'CustomersRegions',
            'WastePricing',
            'Deposits',
            'Ledger',
            'Pickups',
            'Withdrawals',
            'Groceries',
            'Programs',
            'Communications',
            'Reporting',
            'AuditReconciliation',
            'Platform',
        ], array_column(Module::cases(), 'value'));
    }

    /**
     * @return iterable<string, array{Module}>
     */
    public static function modules(): iterable
    {
        foreach (Module::cases() as $module) {
            yield $module->value => [$module];
        }
    }

    #[DataProvider('modules')]
    public function test_each_domain_module_has_a_psr_4_namespace_and_directory(Module $module): void
    {
        self::assertStringStartsWith('App\\Domain\\', $module->namespace());
        self::assertDirectoryExists(base_path($module->relativePath()));
    }
}
