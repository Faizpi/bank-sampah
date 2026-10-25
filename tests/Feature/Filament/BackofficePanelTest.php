<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\CustomerProfile;
use App\Filament\Resources\AuditReconciliation\Models\AuditLogs\AuditLogResource;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource;
use App\Filament\Resources\CustomersRegions\Models\Rts\RtResource;
use App\Filament\Resources\CustomersRegions\Models\Rws\RwResource;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource;
use App\Filament\Resources\Deposits\Models\Deposits\DepositResource;
use App\Filament\Resources\Groceries\Models\GroceryPackages\GroceryPackageResource;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Filament\Resources\Identity\Models\PasswordAssistances\PasswordAssistanceResource;
use App\Filament\Resources\Identity\Models\SessionInventories\SessionInventoryResource;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Filament\Resources\Ledger\Models\BalanceHolds\BalanceHoldResource;
use App\Filament\Resources\Ledger\Models\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\MobileServices\Models\MobileServices\MobileServiceResource;
use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Filament\Resources\Statistics\Models\StatisticPublications\StatisticPublicationResource;
use App\Filament\Resources\WasteMaster\Models\WasteCategories\WasteCategoryResource;
use App\Filament\Resources\WasteMaster\Models\WasteConditions\WasteConditionResource;
use App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\WasteTypeResource;
use App\Filament\Resources\WasteMaster\Models\WasteUnits\WasteUnitResource;
use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\RoleFixtures;
use Tests\TestCase;

final class BackofficePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_backoffice_content_width_matches_the_admin_container_contract(): void
    {
        self::assertSame('max-w-[100rem]', Filament::getPanel('backoffice')->getMaxContentWidth());
    }

    public function test_backoffice_uses_the_shared_restrained_forest_palette(): void
    {
        $forest = [
            50 => '#F3F7F4',
            100 => '#E2ECE6',
            200 => '#C7D9CF',
            300 => '#A3BEAF',
            400 => '#729B87',
            500 => '#477B67',
            600 => '#185746',
            700 => '#123D32',
            800 => '#0F3028',
            900 => '#0A251E',
            950 => '#061712',
        ];
        $colors = Filament::getPanel('backoffice')->getColors();

        self::assertSame($forest, $colors['primary']);
        self::assertSame($forest, $colors['success']);
        self::assertSame('#FBF2DC', $colors['warning'][50]);
        self::assertSame('#D6A84B', $colors['warning'][400]);
        self::assertSame('#8D6726', $colors['warning'][600]);
    }

    public function test_backoffice_theme_compiles_shared_actions_at_a_usable_size(): void
    {
        $theme = file_get_contents(resource_path('css/filament/backoffice/theme.css'));

        self::assertIsString($theme);
        self::assertStringContainsString("@source '../../../../app/Providers/Filament/BackofficePanelProvider.php';", $theme);
        self::assertStringContainsString("@source '../../../../resources/views/components/ui/**/*.blade.php';", $theme);
        self::assertStringContainsString("@source '../../../../resources/views/livewire/treasurer/**/*.blade.php';", $theme);
        self::assertStringContainsString('--spacing-touch: 2.75rem;', $theme);
        self::assertStringContainsString('--spacing-admin-control: 2.5rem;', $theme);
        self::assertStringContainsString('min-height: var(--spacing-admin-control);', $theme);
        self::assertStringContainsString('--spacing-form-textarea: 7.5rem;', $theme);
        self::assertStringContainsString('.backoffice-form-control {', $theme);
        self::assertStringContainsString('px-4 py-2', $theme);
        self::assertStringContainsString("input.backoffice-form-control[type='datetime-local']", $theme);
        self::assertStringNotContainsString('.fi-modal .fi-color-success {', $theme);
        self::assertStringNotContainsString('.fi-modal .fi-color-warning {', $theme);
    }

    public function test_technical_forms_use_the_spacious_shared_control_style(): void
    {
        foreach ([
            'filament/backoffice/technical-settings.blade.php',
            'filament/backoffice/technical-maintenance.blade.php',
            'filament/backoffice/technical-backups.blade.php',
            'filament/backoffice/technical-audit-retention.blade.php',
            'filament/backoffice/technical-media-retention.blade.php',
        ] as $view) {
            $contents = file_get_contents(resource_path('views/'.$view));

            self::assertIsString($contents);
            self::assertStringContainsString('mt-2 backoffice-form-control', $contents);
            self::assertStringNotContainsString('rounded-lg border-gray-300 text-sm shadow-sm', $contents);
        }
    }

    public function test_dense_backoffice_hubs_keep_fluid_operational_surfaces_and_responsive_copy(): void
    {
        $health = file_get_contents(resource_path('views/filament/backoffice/technical-health.blade.php'));
        $reconciliation = file_get_contents(resource_path('views/filament/backoffice/reconciliation.blade.php'));
        $directory = file_get_contents(resource_path('views/filament/backoffice/directory.blade.php'));

        self::assertIsString($health);
        self::assertStringContainsString('<dl class="mt-4 grid w-full min-w-0 grid-cols-[repeat(auto-fit,minmax(min(100%,12rem),1fr))] gap-3">', $health);
        self::assertStringContainsString('<dt class=', $health);
        self::assertStringContainsString('<dd class=', $health);
        self::assertStringContainsString('[overflow-wrap:anywhere]', $health);
        self::assertStringNotContainsString('sm:grid-cols-2 xl:grid-cols-5', $health);

        self::assertIsString($reconciliation);
        self::assertStringContainsString('grid gap-4 sm:grid-cols-2', $reconciliation);
        self::assertStringContainsString('mt-2 grid gap-2 sm:grid-cols-3', $reconciliation);
        self::assertStringContainsString('Catat hasil pemeriksaan', $reconciliation);
        self::assertStringNotContainsString('Ajukan pemeriksaan', $reconciliation);
        self::assertStringNotContainsString('min-w-[46rem]', $reconciliation);

        self::assertIsString($directory);
        self::assertStringContainsString('flex min-w-0 flex-col items-start gap-4 sm:flex-row sm:items-center', $directory);
        self::assertStringContainsString('max-w-2xl text-sm text-text-secondary [overflow-wrap:anywhere]', $directory);
    }

    public function test_dormant_technical_forms_keep_accessible_validation_and_safe_responsive_actions(): void
    {
        $contracts = [
            'technical-settings.blade.php' => [
                'settings-queue-backlog-threshold' => 'settings.queue_backlog_threshold',
                'settings-backup-max-age-hours' => 'settings.backup_max_age_hours',
            ],
            'technical-backups.blade.php' => [
                'backup-database-alias' => 'backupDatabaseAlias',
                'backup-media-alias' => 'backupMediaAlias',
                'backup-database-sha256' => 'backupDatabaseSha256',
                'backup-media-sha256' => 'backupMediaSha256',
                'backup-database-size-bytes' => 'backupDatabaseSizeBytes',
                'backup-media-size-bytes' => 'backupMediaSizeBytes',
                'backup-retention-until' => 'backupRetentionUntil',
                'backup-operator-key' => 'backupOperatorKey',
                'restore-backup-id' => 'restoreBackupId',
                'restore-target-alias' => 'restoreTargetAlias',
                'restore-evidence-reference' => 'restoreEvidenceReference',
                'restore-result' => 'restoreResult',
            ],
            'technical-maintenance.blade.php' => [
                'maintenance-reason' => 'maintenanceReason',
            ],
        ];

        foreach ($contracts as $view => $fields) {
            $contents = file_get_contents(resource_path('views/filament/backoffice/'.$view));

            self::assertIsString($contents);
            self::assertStringContainsString('w-full max-w-3xl', $contents);
            self::assertStringContainsString('wire:loading.attr="disabled"', $contents);
            self::assertStringContainsString('w-full', $contents);
            self::assertStringContainsString('sm:w-auto', $contents);

            foreach ($fields as $id => $property) {
                self::assertStringContainsString($id, $contents);
                self::assertStringContainsString($property, $contents);
                self::assertStringContainsString('aria-invalid="true"', $contents);
                self::assertStringContainsString('aria-describedby=', $contents);
                self::assertStringContainsString('role="alert"', $contents);
            }
        }
    }

    public function test_backoffice_hubs_use_the_shared_light_intro_surface(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $this->actingAs($admin->fresh());

        foreach ([
            '/backoffice/directory',
            '/backoffice/regions',
            '/backoffice/waste-catalog',
            '/backoffice/reports',
            '/backoffice/work-queue-dashboard',
            '/backoffice/technical-health-page',
        ] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('backoffice-page-intro');
        }
    }

    public function test_audited_backoffice_section_navigation_is_native_focusable_and_route_aware(): void
    {
        foreach ([
            'regions.blade.php' => ['Bagian wilayah', "routeIs('filament.backoffice.resources.customers-regions.models.service-areas.*')"],
            'directory.blade.php' => ['Bagian direktori', "routeIs('filament.backoffice.resources.identity.models.customers.*')"],
            'waste-catalog.blade.php' => ['Bagian katalog sampah', "routeIs('filament.backoffice.resources.waste-master.models.waste-categories.*')"],
            'operations-dashboard.blade.php' => ['Administrasi sistem', "routeIs('filament.backoffice.pages.technical-health-page')"],
        ] as $view => [$label, $routeFamily]) {
            $contents = file_get_contents(resource_path('views/filament/backoffice/'.$view));

            self::assertIsString($contents);
            self::assertStringContainsString('<x-backoffice.section-navigation label="'.$label.'"', $contents);
            self::assertStringContainsString('<x-backoffice.section-link', $contents);
            self::assertStringNotContainsString('overflow-x-auto', $contents);
            self::assertStringNotContainsString('Gunakan tombol Tab', $contents);
            self::assertStringContainsString($routeFamily, $contents);
            self::assertStringContainsString(':active=', $contents);
            self::assertStringNotContainsString('role="tab', $contents);
        }
    }

    public function test_reconciliation_comparison_uses_definitions_without_horizontal_overflow(): void
    {
        $contents = file_get_contents(resource_path('views/filament/backoffice/reconciliation.blade.php'));

        self::assertIsString($contents);
        self::assertStringContainsString('<dl', $contents);
        self::assertStringContainsString('<dt>', $contents);
        self::assertStringContainsString('<dd', $contents);
        self::assertStringContainsString('wire:key="reconciliation-', $contents);
        self::assertStringContainsString('aria-label="Rincian keuangan"', $contents);
        self::assertStringNotContainsString('min-w-[', $contents);
    }

    public function test_work_queue_only_renders_queues_with_pending_work(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $pendingCitizen = User::factory()->pendingVerification()->create();
        CustomerProfile::factory()->for($pendingCitizen)->create();

        $this->actingAs($admin->fresh());

        $this->get('/backoffice/work-queue-dashboard')
            ->assertOk()
            ->assertSee('Verifikasi warga')
            ->assertSee('data-disclosure-chevron')
            ->assertDontSee('Pickup hari ini')
            ->assertDontSee('Pencairan menunggu keputusan')
            ->assertDontSee('Setoran perlu ditinjau');
    }

    public function test_backoffice_disclosures_have_visible_expansion_cues(): void
    {
        foreach (['work-queue-dashboard.blade.php', 'technical-backups.blade.php'] as $view) {
            $contents = file_get_contents(resource_path('views/filament/backoffice/'.$view));

            self::assertIsString($contents);
            self::assertStringContainsString('<details class="group', $contents);
            self::assertStringContainsString('data-disclosure-chevron', $contents);
            self::assertStringContainsString('group-open:rotate-180', $contents);
        }
    }

    public function test_every_registered_backoffice_resource_and_page_renders_for_admin(): void
    {
        config()->set('telegram.enabled', true);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static function (LaravelRoute $route): bool {
                $name = $route->getName() ?? '';

                return in_array('GET', $route->methods(), true)
                    && (str_starts_with($name, 'filament.backoffice.resources.')
                        || str_starts_with($name, 'filament.backoffice.pages.'));
            });

        self::assertNotEmpty($routes);

        $this->actingAs($admin->fresh());

        foreach ($routes as $route) {
            $this->get('/'.$route->uri())
                ->assertOk();
        }
    }

    public function test_backoffice_navigation_has_the_focused_taxonomy_groups_in_order(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame(
            [
                'Operasional',
                'Data Master',
                'Program',
                'Pengawasan',
                'Keamanan & Akses',
                'Administrasi sistem',
            ],
            collect($panel->getNavigationGroups())->map(static fn ($group): string => $group->getLabel())->all(),
        );

        $groups = collect($panel->getNavigationGroups())->keyBy(static fn ($group): string => $group->getLabel());

        self::assertFalse($groups['Operasional']->isCollapsed());
        foreach (['Data Master', 'Program', 'Pengawasan', 'Keamanan & Akses', 'Administrasi sistem'] as $group) {
            self::assertTrue($groups[$group]->isCollapsed());
        }
    }

    public function test_admin_navigation_uses_the_stable_resource_order_for_each_group(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $panel = Filament::getPanel('backoffice');

        $this->actingAs($admin->fresh());

        self::assertSame(
            ['Setoran', 'Penjemputan', 'Pencairan', 'Penukaran Sembako'],
            $this->navigationLabelsForGroup($panel, 'Operasional'),
        );
        self::assertSame(
            ['Pengumuman', 'Target Pengumpulan', 'Layanan Keliling', 'Statistik Publik'],
            $this->navigationLabelsForGroup($panel, 'Program'),
        );
        self::assertSame(
            ['Rekonsiliasi', 'Laporan', 'Riwayat aktivitas', 'Perubahan saldo', 'Dana ditahan'],
            $this->navigationLabelsForGroup($panel, 'Pengawasan'),
        );
        self::assertSame(
            ['Bantuan Kata Sandi', 'Sesi Pengguna'],
            $this->navigationLabelsForGroup($panel, 'Keamanan & Akses'),
        );
        self::assertSame(
            ['Direktori', 'Nasabah', 'Pengguna', 'Verifikasi Warga', 'Wilayah', 'Area Pelayanan', 'Dusun', 'RW', 'RT', 'Katalog Sampah', 'Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Harga Sampah', 'Satuan Sampah', 'Paket Sembako'],
            $this->navigationLabelsForGroup($panel, 'Data Master', includeChildren: true),
        );
        self::assertSame(['Kondisi sistem'], $this->navigationLabelsForGroup($panel, 'Administrasi sistem'));
    }

    public function test_backoffice_panel_registers_only_current_resources_without_scanning_stale_files(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame([], $panel->getResourceDirectories());
        self::assertSame([], $panel->getResourceNamespaces());
        self::assertEqualsCanonicalizing([
            AnnouncementResource::class,
            CitizenVerificationResource::class,
            CustomerResource::class,
            UserResource::class,
            PasswordAssistanceResource::class,
            SessionInventoryResource::class,
            MobileServiceResource::class,
            PickupRequestResource::class,
            CollectionTargetResource::class,
            DusunResource::class,
            RwResource::class,
            RtResource::class,
            ServiceAreaResource::class,
            WasteCategoryResource::class,
            WasteConditionResource::class,
            WasteTypeResource::class,
            WasteUnitResource::class,
            WastePriceResource::class,
            WithdrawalRequestResource::class,
            GroceryPackageResource::class,
            StatisticPublicationResource::class,
            GroceryRedemptionResource::class,
            AuditLogResource::class,
            DepositResource::class,
            LedgerEntryResource::class,
            BalanceHoldResource::class,
        ], array_values($panel->getResources()));
    }

    /** @return list<string> */
    private function dataMasterNavigationLabels(Panel $panel): array
    {
        return $this->navigationLabelsForGroup($panel, 'Data Master', sortLabels: true, includeChildren: true);
    }

    /** @return list<string> */
    private function navigationLabelsForGroup(Panel $panel, string $label, bool $sortLabels = false, bool $includeChildren = false): array
    {
        app()->forgetInstance(NavigationManager::class);

        foreach ($panel->getNavigation() as $group) {
            if ($group->getLabel() !== $label) {
                continue;
            }

            $labels = $group->getItems()->flatMap(function ($item) use ($includeChildren): array {
                $labels = [$item->getLabel()];

                if ($includeChildren) {
                    foreach ($item->getChildItems() as $childItem) {
                        $labels[] = $childItem->getLabel();
                    }
                }

                return $labels;
            })->all();

            if ($sortLabels) {
                sort($labels);
            }

            return $labels;
        }

        return [];
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $user = RoleFixtures::assign($user, $permissionNames, $roleName);
    }
}
