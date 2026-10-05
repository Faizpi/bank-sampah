<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Filament\Pages\OperationsDashboard;
use App\Filament\Pages\Reconciliation;
use App\Filament\Pages\Reports as ReportsPage;
use App\Filament\Pages\StatisticsDashboard;
use App\Filament\Pages\TechnicalAuditRetentionPage;
use App\Filament\Pages\TechnicalBackupsPage;
use App\Filament\Pages\TechnicalHealthPage;
use App\Filament\Pages\TechnicalMaintenancePage;
use App\Filament\Pages\TechnicalMediaRetentionPage;
use App\Filament\Pages\TechnicalSettingsPage;
use App\Filament\Pages\WorkQueueDashboard;
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
use App\Filament\Resources\Identity\Models\Permissions\PermissionResource;
use App\Filament\Resources\Identity\Models\Roles\RoleResource;
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
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class BackofficePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_superadmin_with_seeded_backoffice_access_can_access_the_technical_panel(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $superadmin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($admin->fresh()->canAccessPanel($panel));
        self::assertTrue($superadmin->fresh()->canAccessPanel($panel));
        self::assertFalse(User::factory()->create()->canAccessPanel($panel));

        $this->get('/backoffice')->assertRedirect('/backoffice/login');
        $this->actingAs($admin->fresh())->get('/backoffice/login')->assertRedirect('/backoffice');
        self::assertNotContains(
            'Kontrol teknis',
            $this->navigationLabelsForGroup($panel, 'Administrasi sistem'),
        );
        self::assertNotContains(
            'Rekonsiliasi',
            $this->navigationLabelsForGroup($panel, 'Pengawasan'),
        );

        $this->actingAs($superadmin->fresh())->get('/backoffice/login')->assertRedirect('/backoffice');
        self::assertSame(
            ['Kondisi sistem'],
            $this->navigationLabelsForGroup($panel, 'Administrasi sistem', includeChildren: true),
        );
        self::assertContains(
            'Rekonsiliasi',
            $this->navigationLabelsForGroup($panel, 'Pengawasan'),
        );
    }

    public function test_statistics_dashboard_is_discovered_permission_gated_and_renders_for_authorized_users(): void
    {
        self::assertContains(StatisticsDashboard::class, array_values(Filament::getPanel('backoffice')->getPages()));

        $viewer = User::factory()->create();
        $statisticsViewer = User::factory()->create();
        $this->grant($viewer, 'backoffice-viewer', 'backoffice.access');
        $this->grant($statisticsViewer, 'statistics-viewer', 'backoffice.access', 'statistics.internal.view');

        $this->actingAs($viewer->fresh());
        self::assertFalse(StatisticsDashboard::canAccess());
        $this->get('/backoffice/statistics-dashboard')->assertForbidden();

        $this->actingAs($statisticsViewer->fresh());
        self::assertTrue(StatisticsDashboard::canAccess());
        $response = $this->get('/backoffice/statistics-dashboard');

        $response
            ->assertOk()
            ->assertSee('Statistik internal');
        self::assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_work_queue_links_pending_withdrawals_to_the_existing_status_filter(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'withdrawal-reviewer', 'backoffice.access', 'withdrawal.approve', 'withdrawal.view');
        $this->actingAs($user->fresh());

        $page = app(WorkQueueDashboard::class);
        $method = new \ReflectionMethod($page, 'getViewData');
        $queues = $method->invoke($page)['queues'];
        $pendingWithdrawal = collect($queues)->firstWhere('label', 'Pencairan menunggu keputusan');

        self::assertIsArray($pendingWithdrawal);
        self::assertSame(
            WithdrawalRequestResource::getUrl('index', ['filters' => ['status' => ['value' => 'menunggu_verifikasi']]]),
            $pendingWithdrawal['href'],
        );
    }

    public function test_only_technical_health_is_discovered_and_permission_gated(): void
    {
        $pages = array_values(Filament::getPanel('backoffice')->getPages());

        self::assertContains(TechnicalHealthPage::class, $pages);
        self::assertNotContains(OperationsDashboard::class, $pages);
        self::assertNotContains(TechnicalSettingsPage::class, $pages);
        self::assertNotContains(TechnicalMaintenancePage::class, $pages);
        self::assertNotContains(TechnicalBackupsPage::class, $pages);
        self::assertNotContains(TechnicalAuditRetentionPage::class, $pages);
        self::assertNotContains(TechnicalMediaRetentionPage::class, $pages);

        $technical = User::factory()->create();
        $viewer = User::factory()->create();
        $this->grant($technical, 'technical-dashboard', 'system.maintenance');
        $this->grant($viewer, 'viewer-dashboard', 'backoffice.access');

        $this->actingAs($technical->fresh());
        self::assertTrue(OperationsDashboard::canAccess());
        $this->actingAs($viewer->fresh());
        self::assertFalse(OperationsDashboard::canAccess());
    }

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
        self::assertStringContainsString('group mt-6 w-full min-w-0', $reconciliation);
        self::assertStringContainsString('mt-7 grid w-full min-w-0', $reconciliation);
        self::assertStringContainsString('w-full min-w-0 rounded-xl', $reconciliation);
        self::assertStringContainsString('max-w-3xl text-base leading-7', $reconciliation);
        self::assertStringNotContainsString('grid max-w-3xl gap-x-6', $reconciliation);

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

    public function test_consolidated_hubs_render_for_authorized_backoffice_users(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $superadmin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());

        $this->actingAs($admin->fresh());
        $this->get('/backoffice/directory')->assertOk()->assertSee('Nasabah');
        $this->get('/backoffice/regions')->assertOk()->assertSee('Area pelayanan');
        $this->get('/backoffice/waste-catalog')->assertOk()->assertSee('Kategori');
        self::assertFalse(Reconciliation::canAccess());

        $this->actingAs($superadmin->fresh());
        $this->get('/backoffice/reconciliation')->assertOk()->assertSee('Rekonsiliasi saldo harian');
        $this->get('/backoffice/technical-health-page')->assertOk()->assertSee('Kondisi sistem');

        foreach ([
            '/backoffice/operations-dashboard',
            '/backoffice/technical-settings-page',
            '/backoffice/technical-maintenance-page',
            '/backoffice/technical-backups-page',
            '/backoffice/technical-audit-retention-page',
            '/backoffice/technical-media-retention-page',
        ] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    public function test_backoffice_hubs_use_the_shared_light_intro_surface(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = User::factory()->create();
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());

        $this->actingAs($superadmin->fresh());

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
            'regions.blade.php' => ['Bagian wilayah', 'regions-navigation-help', "routeIs('filament.backoffice.resources.customers-regions.models.service-areas.*')"],
            'directory.blade.php' => ['Bagian direktori', 'directory-navigation-help', "routeIs('filament.backoffice.resources.identity.models.customers.*')"],
            'waste-catalog.blade.php' => ['Bagian katalog sampah', 'waste-catalog-navigation-help', "routeIs('filament.backoffice.resources.waste-master.models.waste-categories.*')"],
            'operations-dashboard.blade.php' => ['Administrasi sistem', 'operations-navigation-help', "routeIs('filament.backoffice.pages.technical-health-page')"],
            'reconciliation.blade.php' => ['Bagian rekonsiliasi', 'reconciliation-navigation-help', "routeIs('filament.backoffice.resources.deposits.models.deposits.*')"],
        ] as $view => [$label, $helpId, $routeFamily]) {
            $contents = file_get_contents(resource_path('views/filament/backoffice/'.$view));

            self::assertIsString($contents);
            self::assertStringContainsString('<nav', $contents);
            self::assertStringContainsString('aria-label="'.$label.'"', $contents);
            self::assertStringContainsString('tabindex="0"', $contents);
            self::assertStringContainsString('aria-describedby="'.$helpId.'"', $contents);
            self::assertStringContainsString('id="'.$helpId.'"', $contents);
            self::assertStringContainsString($routeFamily, $contents);
            self::assertStringContainsString('aria-current="page"', $contents);
            self::assertStringNotContainsString('role="tab', $contents);
        }
    }

    public function test_reconciliation_comparison_has_unique_captioned_keyboard_overflow_regions(): void
    {
        $contents = file_get_contents(resource_path('views/filament/backoffice/reconciliation.blade.php'));

        self::assertIsString($contents);
        self::assertStringContainsString('id="reconciliation-comparison-help"', $contents);
        self::assertStringContainsString('tabindex="0"', $contents);
        self::assertStringContainsString('aria-describedby="reconciliation-comparison-help reconciliation-comparison-caption-', $contents);
        self::assertStringContainsString('<caption id="reconciliation-comparison-caption-', $contents);
        self::assertStringContainsString('Rincian pembanding rekonsiliasi', $contents);
        self::assertStringContainsString('Geser secara horizontal', $contents);
    }

    public function test_admin_and_superadmin_can_open_the_permission_gated_report_page(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $superadmin = User::factory()->create();
        $panelViewer = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());
        $this->grant($panelViewer, 'report-panel-viewer', 'backoffice.access');

        self::assertContains(ReportsPage::class, array_values(Filament::getPanel('backoffice')->getPages()));

        $this->actingAs($panelViewer->fresh());
        self::assertFalse(ReportsPage::canAccess());

        $this->actingAs($admin->fresh());
        self::assertTrue(ReportsPage::canAccess());
        $this->get('/backoffice/reports')
            ->assertOk()
            ->assertSee('Ringkasan transaksi dan ekspor Excel')
            ->assertSee('Unduh Excel')
            ->assertDontSee('CSV')
            ->assertDontSee('PDF');

        $this->actingAs($superadmin->fresh());
        self::assertTrue(ReportsPage::canAccess());
        $this->get('/backoffice/reports')
            ->assertOk()
            ->assertSee('Ringkasan transaksi dan ekspor Excel')
            ->assertSee('Unduh Excel');
    }

    public function test_work_queue_only_renders_queues_with_pending_work(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
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

    public function test_every_registered_backoffice_resource_and_page_renders_for_superadmin(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $superadmin = User::factory()->create();
        $superadmin->roles()->attach(Role::query()->where('name', 'superadmin')->sole());

        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static function (LaravelRoute $route): bool {
                $name = $route->getName() ?? '';

                return in_array('GET', $route->methods(), true)
                    && (str_starts_with($name, 'filament.backoffice.resources.')
                        || str_starts_with($name, 'filament.backoffice.pages.'));
            });

        self::assertNotEmpty($routes);

        $this->actingAs($superadmin->fresh());

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
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->sole());
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
            ['Laporan'],
            $this->navigationLabelsForGroup($panel, 'Pengawasan'),
        );
        self::assertSame(
            ['Bantuan Kata Sandi', 'Sesi Pengguna', 'Peran', 'Izin'],
            $this->navigationLabelsForGroup($panel, 'Keamanan & Akses'),
        );
        self::assertSame(
            ['Direktori', 'Nasabah', 'Pengguna', 'Verifikasi Warga', 'Wilayah', 'Area Pelayanan', 'Dusun', 'RW', 'RT', 'Katalog Sampah', 'Jenis Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Harga Sampah', 'Satuan Sampah', 'Paket Sembako'],
            $this->navigationLabelsForGroup($panel, 'Data Master', includeChildren: true),
        );
        self::assertSame([], $this->navigationLabelsForGroup($panel, 'Administrasi sistem'));
    }

    public function test_backoffice_panel_discovers_only_the_regional_resources(): void
    {
        $panel = Filament::getPanel('backoffice');

        self::assertSame([app_path('Filament/Resources')], $panel->getResourceDirectories());
        self::assertSame(['App\\Filament\\Resources'], $panel->getResourceNamespaces());
        self::assertEqualsCanonicalizing([
            AnnouncementResource::class,
            CitizenVerificationResource::class,
            CustomerResource::class,
            UserResource::class,
            PasswordAssistanceResource::class,
            SessionInventoryResource::class,
            PermissionResource::class,
            RoleResource::class,
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

    public function test_only_authorized_panel_users_see_regional_resources_under_data_master(): void
    {
        $regionalManager = User::factory()->create();
        $panelUserWithoutRegionalPermission = User::factory()->create();
        $this->grant($regionalManager, 'regional-admin', 'backoffice.access', 'region.manage');
        $this->grant($panelUserWithoutRegionalPermission, 'backoffice-user', 'backoffice.access');

        $panel = Filament::getPanel('backoffice');

        self::assertTrue($regionalManager->fresh()->canAccessPanel($panel));
        $this->actingAs($regionalManager->fresh());
        self::assertSame(['Area Pelayanan', 'Dusun', 'RT', 'RW', 'Wilayah'], $this->dataMasterNavigationLabels($panel));

        self::assertTrue($panelUserWithoutRegionalPermission->fresh()->canAccessPanel($panel));
        $this->actingAs($panelUserWithoutRegionalPermission->fresh());
        self::assertSame([], $this->dataMasterNavigationLabels($panel));
    }

    public function test_identity_resources_remain_visible_only_to_users_with_existing_permissions(): void
    {
        $customerManager = User::factory()->create();
        $userViewer = User::factory()->create();
        $panelUserWithoutIdentityPermission = User::factory()->create();
        $this->grant($customerManager, 'customer-manager', 'backoffice.access', 'customer.view', 'customer.update');
        $this->grant($userViewer, 'user-viewer', 'backoffice.access', 'user.view');
        $this->grant($panelUserWithoutIdentityPermission, 'identity-unprivileged', 'backoffice.access');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($customerManager->fresh());
        self::assertSame(['Direktori', 'Nasabah'], $this->dataMasterNavigationLabels($panel));

        $this->actingAs($userViewer->fresh());
        self::assertSame(['Direktori', 'Pengguna', 'Verifikasi Warga'], $this->dataMasterNavigationLabels($panel));

        $this->actingAs($panelUserWithoutIdentityPermission->fresh());
        self::assertSame([], $this->dataMasterNavigationLabels($panel));
    }

    public function test_only_waste_viewers_and_managers_see_waste_resources_with_mutation_reserved_for_managers(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'waste-viewer', 'backoffice.access', 'waste.view');
        $this->grant($manager, 'waste-manager', 'backoffice.access', 'waste.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertSame(['Jenis Sampah', 'Katalog Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WasteCategory::class));

        $this->actingAs($manager->fresh());
        self::assertSame(['Jenis Sampah', 'Katalog Sampah', 'Kategori Sampah', 'Kondisi Sampah', 'Satuan Sampah'], $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WasteCategory::class));
    }

    public function test_price_navigation_requires_price_view_and_creation_requires_price_manage(): void
    {
        $viewer = User::factory()->create();
        $manager = User::factory()->create();
        $this->grant($viewer, 'price-viewer', 'backoffice.access', 'price.view');
        $this->grant($manager, 'price-manager', 'backoffice.access', 'price.manage');

        $panel = Filament::getPanel('backoffice');

        $this->actingAs($viewer->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertFalse($viewer->fresh()->can('create', WastePrice::class));

        $this->actingAs($manager->fresh());
        self::assertContains('Harga Sampah', $this->dataMasterNavigationLabels($panel));
        self::assertTrue($manager->fresh()->can('create', WastePrice::class));
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
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => "Test role {$roleName}"],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);
    }
}
