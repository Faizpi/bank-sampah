<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupItem;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Models\StatusHistory;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Shared\Weight;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Builds a coherent local dataset for manual end-to-end testing.
 *
 * This is deliberately not called in production. All business identifiers use
 * a Sindangheula prefix and every lookup is idempotent, so re-running db:seed is safe.
 */
final class LocalDataSeeder extends Seeder
{
    /** @var list<string> */
    private const CUSTOMER_NAMES = [
        'Asep Saepuloh', 'Ujang Suherman',
        'Nia Kurniasih', 'Fajar Nugraha', 'Yani Mulyani', 'Lilis Suryani', 'Rudi Hartono',
        'Euis Komariah', 'Budi Santoso', 'Tati Rosdiana', 'Hendra Gunawan', 'Wulan Sari', 'Deni Permana',
        'Yayah Rohayah', 'Iwan Kurniawan', 'Maman Suparman', 'Novi Andriani', 'Cecep Saefulloh', 'Fitri Handayani',
        'Taufik Hidayat', 'Mira Puspitasari', 'Dede Suhendar', 'Salsa Nuraini', 'Rian Firmansyah', 'Endah Wulandari',
        'Rizal Maulana', 'Neng Sulastri', 'Wahyu Ramadhan', 'Intan Permata', 'Yudi Irawan', 'Mela Anggraini',
        'Raka Pratama', 'Nurhayati', 'Dimas Saputra', 'Sari Rahmawati', 'Aldi Firmansyah', 'Mutiara Fitri',
        'Robby Kurnia', 'Novianti', 'Gilang Ramadhan', 'Tina Kartika', 'Solehudin', 'Maya Safitri',
    ];

    public function run(): void
    {
        DeveloperUsersSeeder::requireDemoDataConfiguration();

        $now = CarbonImmutable::now('Asia/Jakarta');
        // All supplemental demo accounts use the configured demo password.
        // Reusing one bcrypt result keeps seed-demo-data below typical shared
        // hosting web-request limits while preserving the same login behavior.
        $passwordHash = Hash::make(DeveloperUsersSeeder::password());
        $admin = User::query()->where('email', DeveloperUsersSeeder::email('admin'))->firstOrFail();
        $regions = $this->seedRegions($admin);
        $staff = $this->seedStaff($regions['areas'], $passwordHash);
        $customers = $this->seedCustomers($regions['rts'], $passwordHash);
        $master = $this->seedWasteMaster($admin);
        $prices = $this->seedPrices($master['types'], $master['conditions'], $admin, $now);
        $mobileServices = $this->seedMobileServices($regions, $staff, $master['types'], $now);
        $pickups = $this->seedPickups($regions, $staff, $customers, $master['types'], $now);

        DB::transaction(function () use ($admin, $customers, $staff, $master, $prices, $mobileServices, $pickups, $regions, $now): void {
            $ledger = app(LedgerService::class);
            foreach ($customers as $customer) {
                $ledger->ensureAccount($customer);
            }

            $this->seedDeposits($customers, $staff, $master['types'], $master['conditions'], $prices, $mobileServices, $pickups, $ledger, $now);
            $this->seedWithdrawals($admin, $customers, $staff, $ledger, $now);
            $this->seedGroceries($admin, $customers, $staff, $ledger, $now);
            $this->seedPrograms($admin, $regions['rts'], $master, $now);
            $this->seedAnnouncements($admin, $regions['rts'], $now);
            $this->seedStatisticPublication($admin);
        });

        $this->command->info('Data lokal siap: '.count($customers).' warga, '.count($regions['rts']).' RT, '.count($master['types']).' jenis sampah, dan histori transaksi 7 hari.');
    }

    /** @return array{dusuns: list<Dusun>, rws: list<Rw>, rts: list<Rt>, areas: list<ServiceArea>} */
    private function seedRegions(User $admin): array
    {
        $manager = app(ManageRegions::class);
        $dusuns = [];
        $rws = [];
        $rts = [];

        foreach ([
            ['code' => 'DSN-SH-UTARA', 'name' => 'Dusun Sindangheula Utara'],
            ['code' => 'DSN-SH-TENGAH', 'name' => 'Dusun Sindangheula Tengah'],
            ['code' => 'DSN-SH-SELATAN', 'name' => 'Dusun Sindangheula Selatan'],
        ] as $dusunData) {
            $dusun = Dusun::query()->where('code', $dusunData['code'])->first();
            $dusun ??= $manager->createDusun($admin, $dusunData['code'], $dusunData['name']);
            $dusuns[] = $dusun;

            foreach (range(1, 2) as $rwNumber) {
                $rwCode = $dusunData['code'].'-RW-'.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT);
                $rw = Rw::query()->where('dusun_id', $dusun->id)->where('code', $rwCode)->first();
                $rw ??= $manager->createRw($admin, $dusun, $rwCode, 'RW '.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT).' '.$dusun->name);
                $rws[] = $rw;

                foreach (range(1, 3) as $rtNumber) {
                    $rtCode = $rwCode.'-RT-'.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT);
                    $rt = Rt::query()->where('rw_id', $rw->id)->where('code', $rtCode)->first();
                    $rt ??= $manager->createRt($admin, $rw, $rtCode, 'RT '.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT).' '.$rw->name);
                    $rts[] = $rt;
                }
            }
        }

        $areaNames = ['Layanan Sindangheula Utara', 'Layanan Sindangheula Tengah', 'Layanan Sindangheula Selatan'];
        $areas = [];
        foreach (array_chunk($rts, 6) as $index => $areaRts) {
            $name = $areaNames[$index];
            $area = ServiceArea::query()->where('name', $name)->first();
            if ($area === null) {
                $area = $manager->createServiceArea($admin, $name, $areaRts);
            } else {
                $manager->updateServiceArea($admin, $area, $name, $areaRts);
            }
            $areas[] = $area;
        }

        return ['dusuns' => $dusuns, 'rws' => $rws, 'rts' => $rts, 'areas' => $areas];
    }

    /** @param list<ServiceArea> $areas
     * @return list<User>
     */
    private function seedStaff(array $areas, string $passwordHash): array
    {
        $role = Role::query()->where('name', 'petugas')->firstOrFail();
        $created = [];
        foreach ([
            ['key' => 'utara', 'name' => 'Rangga Pratama', 'phone' => '6281312345001', 'email' => 'rangga.pratama@sindangheula.test', 'staff_number' => 'STF-SH-101', 'area_index' => 0],
            ['key' => 'selatan', 'name' => 'Nina Kusumawati', 'phone' => '6281312345002', 'email' => 'nina.kusumawati@sindangheula.test', 'staff_number' => 'STF-SH-102', 'area_index' => 2],
            ['key' => 'tengah', 'name' => 'Yusuf Maulana', 'phone' => '6281312345003', 'email' => 'yusuf.maulana@sindangheula.test', 'staff_number' => 'STF-SH-103', 'area_index' => 1],
        ] as $definition) {
            $user = User::query()->updateOrCreate(
                ['phone' => $definition['phone']],
                [
                    'name' => $definition['name'],
                    'email' => $definition['email'],
                    'email_verified_at' => now(),
                    'password' => $passwordHash,
                    'status' => UserStatus::Active,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Data awal Sindangheula']]);
            StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['staff_number' => $definition['staff_number'], 'service_area_id' => $areas[$definition['area_index']]->id, 'active_from' => today()->subDays(30), 'active_to' => null],
            );
            $created[$definition['key']] = $user;
        }

        $petugas = User::query()->where('email', DeveloperUsersSeeder::email('petugas'))->firstOrFail();
        StaffProfile::query()->updateOrCreate(
            ['user_id' => $petugas->id],
            ['staff_number' => 'STF-SH-001', 'service_area_id' => $areas[1]->id, 'active_from' => today()->subYear(), 'active_to' => null],
        );
        $treasurer = User::query()->where('email', DeveloperUsersSeeder::email('bendahara'))->firstOrFail();

        return [$created['utara'], $petugas, $created['selatan'], $created['tengah'], $treasurer];
    }

    /** @param list<Rt> $rts
     * @return list<User>
     */
    private function seedCustomers(array $rts, string $passwordHash): array
    {
        $role = Role::query()->where('name', 'warga')->firstOrFail();
        $customers = [User::query()->where('email', DeveloperUsersSeeder::email('warga'))->firstOrFail()];
        $addresses = ['Kampung Cikadu', 'Kampung Babakan', 'Kampung Pasirhuni', 'Kampung Sukamaju', 'Kampung Cibogo', 'Kampung Kiarapyaung'];
        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $number = $index + 2;
            $user = User::query()->updateOrCreate(
                ['phone' => '628140000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'email' => 'warga.'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'@sindangheula.test',
                    'email_verified_at' => now(),
                    'password' => $passwordHash,
                    'status' => UserStatus::Active,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Data awal Sindangheula']]);
            $profile = CustomerProfile::query()->firstOrNew(['user_id' => $user->id]);
            $token = $profile->qr_token_hash === null ? QrToken::generate() : null;
            $profile->forceFill([
                'customer_number' => $profile->customer_number ?? 'CST-'.str_pad((string) $number, 8, '0', STR_PAD_LEFT),
                'rt_id' => $rts[$index % count($rts)]->id,
                'address' => $addresses[$index % count($addresses)].', '.$rts[$index % count($rts)]->name.', Desa Sindangheula',
                'joined_at' => today()->subDays(7 - ($index % 7)),
                'qr_token_hash' => $profile->qr_token_hash ?? $token?->hash(),
                'qr_token_encrypted' => $profile->qr_token_encrypted ?? $token?->value(),
                'qr_rotated_at' => $profile->qr_rotated_at ?? now(),
            ])->save();
            $customers[] = $user;
        }

        return $customers;
    }

    /** @return array{categories: list<WasteCategory>, types: list<WasteType>, conditions: list<WasteCondition>} */
    private function seedWasteMaster(User $admin): array
    {
        $manager = app(ManageWasteMaster::class);
        $categoryDefinitions = [
            ['code' => 'PLASTIK', 'name' => 'Plastik'],
            ['code' => 'KERTAS', 'name' => 'Kertas'],
            ['code' => 'LOGAM', 'name' => 'Logam'],
            ['code' => 'KACA', 'name' => 'Kaca'],
        ];
        $categories = [];
        foreach ($categoryDefinitions as $definition) {
            $category = WasteCategory::query()->where('code', $definition['code'])->first();
            $category ??= $manager->createCategory($admin, $definition['code'], $definition['name']);
            if (! $category->is_active) {
                $manager->activate($admin, $category);
            }
            $categories[$definition['code']] = $category;
        }

        $unit = WasteUnit::query()->where('code', 'KG')->first();
        $unit ??= $manager->createUnit($admin, 'KG', 'Kilogram', 'kg', WasteUnit::CLASSIFICATION_WEIGHT, '1.000000');
        if (! $unit->is_active) {
            $manager->activate($admin, $unit);
        }
        $conditions = [];
        foreach ([['code' => 'BERSIH', 'name' => 'Bersih'], ['code' => 'CAMPUR', 'name' => 'Campur']] as $definition) {
            $condition = WasteCondition::query()->where('code', $definition['code'])->first();
            $condition ??= $manager->createCondition($admin, $definition['code'], $definition['name'], 'Kondisi material untuk pencatatan setoran.');
            if (! $condition->is_active) {
                $manager->activate($admin, $condition);
            }
            $conditions[$definition['code']] = $condition;
        }

        $types = [];
        $typeDefinitions = [
            ['category' => 'PLASTIK', 'code' => 'PET-BOTOL', 'name' => 'Botol PET', 'plastic' => true],
            ['category' => 'PLASTIK', 'code' => 'GELAS-PLASTIK', 'name' => 'Gelas Plastik', 'plastic' => true],
            ['category' => 'PLASTIK', 'code' => 'PLASTIK-CAMPUR', 'name' => 'Plastik Campur', 'plastic' => true],
            ['category' => 'KERTAS', 'code' => 'KARDUS', 'name' => 'Kardus', 'plastic' => false],
            ['category' => 'KERTAS', 'code' => 'KERTAS-PUTIH', 'name' => 'Kertas Putih', 'plastic' => false],
            ['category' => 'LOGAM', 'code' => 'BESI', 'name' => 'Besi', 'plastic' => false],
            ['category' => 'LOGAM', 'code' => 'ALUMINIUM', 'name' => 'Aluminium', 'plastic' => false],
            ['category' => 'KACA', 'code' => 'KACA-BOTOL', 'name' => 'Botol Kaca', 'plastic' => false],
        ];
        foreach ($typeDefinitions as $index => $definition) {
            $type = WasteType::query()->where('code', $definition['code'])->first();
            if ($type === null) {
                $type = $manager->createType($admin, $categories[$definition['category']], $unit, $definition['code'], $definition['name'], 'Material terpilah yang diterima Bank Sampah Sindangheula.', $index + 1, $definition['plastic'], true, array_values(array_map(static fn (WasteCondition $condition): int => $condition->id, $conditions)));
            }
            $types[] = $type;
        }

        return ['categories' => array_values($categories), 'types' => $types, 'conditions' => array_values($conditions)];
    }

    /** @param list<WasteType> $types
     * @param  list<WasteCondition>  $conditions
     * @return array<int, array<int, WastePrice>>
     */
    private function seedPrices(array $types, array $conditions, User $admin, CarbonImmutable $now): array
    {
        $basePrices = [1800, 2200, 2600, 1500, 1800, 3000, 9000, 1200];
        $prices = [];
        foreach ($types as $typeIndex => $type) {
            foreach ($conditions as $conditionIndex => $condition) {
                $priceValue = $basePrices[$typeIndex] - ($conditionIndex * 250);
                $from = $now->subDays(30)->startOfDay();
                $price = WastePrice::query()
                    ->where('waste_type_id', $type->id)
                    ->where('waste_condition_id', $condition->id)
                    ->where('effective_from', $from)
                    ->first();
                if ($price === null) {
                    $price = WasteMasterMutationGuard::run(fn (): WastePrice => WastePrice::query()->create([
                        'waste_type_id' => $type->id,
                        'waste_condition_id' => $condition->id,
                        'price' => max(500, $priceValue),
                        'effective_from' => $from,
                        'effective_to' => null,
                        'created_by' => $admin->id,
                        'rounding_version' => 'half_up_v1',
                    ]));
                }
                $prices[$type->id][$condition->id] = $price;
            }
        }

        return $prices;
    }

    /** @param array{rws: list<Rw>, rts: list<Rt>} $regions
     * @param  list<User>  $staff
     * @param  list<WasteType>  $types
     * @return list<MobileService>
     */
    private function seedMobileServices(array $regions, array $staff, array $types, CarbonImmutable $now): array
    {
        $services = [];
        $definitions = [
            ['number' => 'MOB-SH-001', 'start' => $now->subDays(6)->setTime(8, 0), 'end' => $now->subDays(6)->setTime(12, 0), 'status' => MobileServiceStatus::Closed, 'point' => 'Balai Dusun Sindangheula Utara'],
            ['number' => 'MOB-SH-002', 'start' => $now->subDays(3)->setTime(8, 0), 'end' => $now->subDays(3)->setTime(13, 0), 'status' => MobileServiceStatus::Closed, 'point' => 'Lapangan Dusun Sindangheula Tengah'],
            ['number' => 'MOB-SH-003', 'start' => $now->subHour(), 'end' => $now->addHours(4), 'status' => MobileServiceStatus::Open, 'point' => 'Halaman Kantor Desa Sindangheula'],
            ['number' => 'MOB-SH-004', 'start' => $now->addDay()->setTime(8, 0), 'end' => $now->addDay()->setTime(13, 0), 'status' => MobileServiceStatus::Published, 'point' => 'Balai Dusun Sindangheula Selatan'],
        ];
        foreach ($definitions as $index => $definition) {
            $service = MobileService::query()->firstOrCreate(
                ['service_number' => $definition['number']],
                [
                    'rw_id' => $regions['rws'][$index % count($regions['rws'])]->id,
                    'rt_id' => $regions['rts'][$index % count($regions['rts'])]->id,
                    'point' => $definition['point'],
                    'starts_at' => $definition['start'],
                    'ends_at' => $definition['end'],
                    'status' => $definition['status'],
                    'capacity' => 30,
                    'served_count' => $definition['status'] === MobileServiceStatus::Closed ? 12 + $index : 0,
                    'notes' => 'Jadwal layanan keliling Bank Sampah Sindangheula.',
                    'created_by' => $staff[0]->id,
                ],
            );
            $service->staff()->syncWithoutDetaching([$staff[$index % 4]->id]);
            $service->wasteTypes()->syncWithoutDetaching(array_map(static fn (WasteType $type): int => $type->id, array_slice($types, 0, 6)));
            $services[] = $service;
        }

        return $services;
    }

    /** @param array{areas: list<ServiceArea>, rts: list<Rt>} $regions
     * @param  list<User>  $staff
     * @param  list<User>  $customers
     * @param  list<WasteType>  $types
     * @return list<PickupRequest>
     */
    private function seedPickups(array $regions, array $staff, array $customers, array $types, CarbonImmutable $now): array
    {
        $pickups = [];
        foreach (range(1, 8) as $number) {
            $areaIndex = ($number - 1) % count($regions['areas']);
            $customer = $customers[($number * 3) % count($customers)];
            $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
            $date = $now->subDays(7 - $number)->toDateString();
            $status = match ($number) {
                1, 2, 3, 4 => PickupStatus::Completed,
                5, 6 => PickupStatus::Scheduled,
                7 => PickupStatus::Accepted,
                default => PickupStatus::PendingReview,
            };
            $pickup = PickupRequest::query()->firstOrCreate(
                ['request_number' => 'PUP-SH-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'rt_id' => $rt->id,
                    'service_area_id' => $regions['areas'][$areaIndex]->id,
                    'address' => (string) $customer->customerProfile()->firstOrFail()->address,
                    'selected_date' => $date,
                    'scheduled_date' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $date : null,
                    'estimated_weight_kg' => number_format(4.5 + ($number * 0.8), 3, '.', ''),
                    'notes' => 'Warga mengajukan penjemputan sampah terpilah.',
                    'status' => $status,
                    'assigned_staff_id' => $staff[$areaIndex % 4]->id,
                    'accepted_at' => $status !== PickupStatus::PendingReview ? $now->subDays(7 - $number)->setTime(9, 0) : null,
                    'scheduled_at' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $now->subDays(7 - $number)->setTime(10, 0) : null,
                    'en_route_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(11, 0) : null,
                    'picked_up_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(11, 30) : null,
                    'completed_at' => $status === PickupStatus::Completed ? $now->subDays(7 - $number)->setTime(12, 0) : null,
                ],
            );
            foreach (array_slice($types, 0, 2) as $typeIndex => $type) {
                PickupItem::query()->firstOrCreate(
                    ['pickup_request_id' => $pickup->id, 'waste_type_id' => $type->id],
                    ['estimated_weight_kg' => number_format(2 + ($number * 0.25) + $typeIndex, 3, '.', ''), 'estimated_quantity' => 3 + $number],
                );
            }
            if (! StatusHistory::query()->where('subject_type', PickupRequest::class)->where('subject_id', $pickup->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => PickupRequest::class, 'subject_id' => $pickup->id, 'old_status' => null, 'new_status' => $status->value, 'actor_id' => $staff[$areaIndex % 4]->id, 'reason' => 'Status awal layanan penjemputan.', 'occurred_at' => $pickup->completed_at ?? $pickup->created_at ?? $now]);
            }
            $pickups[] = $pickup;
        }

        foreach ($regions['areas'] as $area) {
            foreach (range(0, 6) as $dayOffset) {
                PickupCapacity::query()->updateOrCreate(
                    ['service_area_id' => $area->id, 'service_date' => $now->addDays($dayOffset + 1)->startOfDay()],
                    ['max_addresses' => 12, 'max_weight_kg' => '80.000', 'vehicle_label' => 'Kendaraan Layanan '.($area->id), 'is_active' => true],
                );
            }
        }

        return $pickups;
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     * @param  list<WasteType>  $types
     * @param  list<WasteCondition>  $conditions
     * @param  array<int, array<int, WastePrice>>  $prices
     * @param  list<MobileService>  $mobileServices
     * @param  list<PickupRequest>  $pickups
     */
    private function seedDeposits(array $customers, array $staff, array $types, array $conditions, array $prices, array $mobileServices, array $pickups, LedgerService $ledger, CarbonImmutable $now): void
    {
        foreach (range(0, 6) as $dayOffset) {
            foreach (range(1, 8) as $sequence) {
                $seedNumber = ($dayOffset * 8) + $sequence;
                $number = 'DEP-SH-'.str_pad((string) $seedNumber, 3, '0', STR_PAD_LEFT);
                if (Deposit::query()->where('deposit_number', $number)->exists()) {
                    continue;
                }
                $customer = $customers[($seedNumber * 5) % count($customers)];
                $staffMember = $staff[($seedNumber - 1) % 4];
                $occurredAt = $now->subDays($dayOffset)->setTime(8 + ($sequence % 8), ($sequence * 7) % 60);
                $mobile = $dayOffset === 3 && $sequence <= 2 ? $mobileServices[1] : ($dayOffset === 0 && $sequence === 1 ? $mobileServices[2] : null);
                $pickup = $sequence === 2 && $dayOffset < 4 ? $pickups[$dayOffset] : null;
                $method = $mobile instanceof MobileService ? 'keliling' : ($pickup instanceof PickupRequest ? 'penjemputan' : 'langsung');
                $token = QrToken::generate();
                $deposit = Deposit::query()->create([
                    'deposit_number' => $number,
                    'customer_id' => $customer->id,
                    'staff_id' => $staffMember->id,
                    'method' => $method,
                    'pickup_request_id' => $pickup?->id,
                    'mobile_service_id' => $mobile?->id,
                    'location' => $mobile instanceof MobileService ? $mobile->point : 'Loket Bank Sampah Sindangheula',
                    'occurred_at' => $occurredAt,
                    'status' => Deposit::STATUS_DRAFT,
                ]);

                $totalGrams = 0;
                $totalValue = 0;
                foreach ([$types[($seedNumber + 1) % count($types)], $types[($seedNumber + 3) % count($types)]] as $itemIndex => $type) {
                    $condition = $conditions[($seedNumber + $itemIndex) % count($conditions)];
                    $weight = number_format(1.2 + (($seedNumber + $itemIndex) % 7) * 0.65, 3, '.', '');
                    $snapshot = $prices[$type->id][$condition->id]->snapshot()->withWeight($weight);
                    $deposit->items()->create([
                        'waste_type_id' => $type->id,
                        'waste_condition_id' => $condition->id,
                        'waste_type_code' => $snapshot->wasteTypeCode,
                        'waste_type_name' => $snapshot->wasteTypeName,
                        'unit_code' => $snapshot->unitCode,
                        'unit_name' => $snapshot->unitName,
                        'unit_symbol' => $snapshot->unitSymbol,
                        'condition_code' => $snapshot->conditionCode,
                        'condition_name' => $snapshot->conditionName,
                        'weight_kg' => $snapshot->weightKg,
                        'price_per_unit' => $snapshot->pricePerUnit,
                        'subtotal' => $snapshot->subtotal,
                        'rounding_version' => $snapshot->roundingVersion,
                        'price_snapshot' => $snapshot->toArray(),
                    ]);
                    $totalGrams += Weight::fromDecimal($snapshot->weightKg)->grams();
                    $totalValue += $snapshot->subtotal;
                }
                $deposit->forceFill([
                    'status' => Deposit::STATUS_FINAL,
                    'total_weight_kg' => Weight::fromGrams($totalGrams)->decimal(),
                    'total_value' => $totalValue,
                    'finalized_at' => $occurredAt->addMinutes(20),
                    'idempotency_key' => 'local-deposit-'.$seedNumber,
                    'verification_token_hash' => $token->hash(),
                    'verification_token_encrypted' => $token->value(),
                ])->save();
                $ledger->postDeposit($deposit, $totalValue, 'deposit:'.$deposit->id.':deposit');
                if ($pickup instanceof PickupRequest && $pickup->deposit_id === null) {
                    $pickup->forceFill(['deposit_id' => $deposit->id])->save();
                }
            }
        }
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     */
    private function seedWithdrawals(User $admin, array $customers, array $staff, LedgerService $ledger, CarbonImmutable $now): void
    {
        foreach (range(1, 6) as $number) {
            $eligibleCustomers = array_values(array_filter($customers, static function (User $candidate): bool {
                return ($candidate->ledgerAccount()->first()?->availableBalance() ?? 0) >= 10_000;
            }));
            if ($eligibleCustomers === []) {
                break;
            }
            $customer = $eligibleCustomers[($number - 1) % count($eligibleCustomers)];
            $available = $customer->ledgerAccount()->firstOrFail()->availableBalance();
            $amount = min(10_000 + ($number * 2_500), intdiv($available, 2_500) * 2_500);
            if ($amount < 10_000) {
                continue;
            }
            $requestNumber = 'WDR-SH-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $withdrawal = WithdrawalRequest::query()->firstOrCreate(
                ['request_number' => $requestNumber],
                [
                    'customer_id' => $customer->id,
                    'requested_by_id' => $customer->id,
                    'amount' => $amount,
                    'status' => WithdrawalStatus::PendingVerification,
                    'pickup_location' => 'Loket Bank Sampah Sindangheula',
                    'pickup_date' => $now->addDays(1)->toDateString(),
                ],
            );
            if ($withdrawal->balance_hold_id === null) {
                $hold = $ledger->createHold($customer, $withdrawal, (int) $withdrawal->amount, 'withdrawal:'.$withdrawal->id.':hold');
                $withdrawal->forceFill(['balance_hold_id' => $hold->id])->save();
            }
            if (! StatusHistory::query()->where('subject_type', WithdrawalRequest::class)->where('subject_id', $withdrawal->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => null, 'new_status' => WithdrawalStatus::PendingVerification->value, 'actor_id' => $customer->id, 'reason' => 'Pengajuan pencairan warga.', 'occurred_at' => $now->subDays(5 - min($number, 5))]);
            }
            if ($number >= 3 && $withdrawal->status === WithdrawalStatus::PendingVerification) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::Approved, 'approver_id' => $admin->id, 'approved_at' => $now->subDays(1)])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::PendingVerification->value, 'new_status' => WithdrawalStatus::Approved->value, 'actor_id' => $admin->id, 'reason' => 'Pencairan telah diverifikasi.', 'occurred_at' => $now->subHours(18)]);
            }
            if ($number >= 5 && $withdrawal->status === WithdrawalStatus::Approved) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::ReadyForPickup, 'payer_id' => $staff[4]->id])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::Approved->value, 'new_status' => WithdrawalStatus::ReadyForPickup->value, 'actor_id' => $staff[4]->id, 'reason' => 'Bendahara ditetapkan sebagai petugas pembayar.', 'occurred_at' => $now->subHours(12)]);
            }
        }
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     */
    private function seedGroceries(User $admin, array $customers, array $staff, LedgerService $ledger, CarbonImmutable $now): void
    {
        $packages = [
            ['code' => 'PKT-SH-HEMAT', 'name' => 'Paket Hemat Harian', 'contents' => "Beras 2 kg\nMinyak goreng 1 liter\nGula pasir 500 gram", 'value' => 8_000],
            ['code' => 'PKT-SH-KELUARGA', 'name' => 'Paket Keluarga', 'contents' => "Beras 3 kg\nMinyak goreng 1 liter\nGula pasir 1 kg\nMi instan 5 bungkus", 'value' => 15_000],
            ['code' => 'PKT-SH-LENGKAP', 'name' => 'Paket Lengkap', 'contents' => "Beras 5 kg\nMinyak goreng 2 liter\nGula pasir 1 kg\nTelur ayam 10 butir\nSarden 2 kaleng", 'value' => 22_000],
        ];
        $packageModels = [];
        foreach ($packages as $packageData) {
            $packageModels[] = GroceryPackage::query()->firstOrCreate(
                ['code' => $packageData['code']],
                $packageData + ['active_from' => $now->subDays(30)->toDateString(), 'active_until' => null, 'status' => 'aktif'],
            );
        }

        $usedCustomerIds = [];
        foreach ([GroceryStatus::PendingVerification, GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup] as $index => $targetStatus) {
            $package = $packageModels[$index % count($packageModels)];
            $eligibleCustomers = array_values(array_filter($customers, static fn (User $candidate): bool => ($candidate->ledgerAccount()->first()?->availableBalance() ?? 0) >= $package->value));
            if ($eligibleCustomers === []) {
                continue;
            }
            $customer = collect($eligibleCustomers)->first(fn (User $candidate): bool => ! in_array($candidate->id, $usedCustomerIds, true)) ?? $eligibleCustomers[0];
            $usedCustomerIds[] = $customer->id;
            $number = $index + 1;
            $redemption = GroceryRedemption::query()->firstOrCreate(
                ['request_number' => 'GRC-SH-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT)],
                [
                    'customer_id' => $customer->id,
                    'requested_by_id' => $customer->id,
                    'grocery_package_id' => $package->id,
                    'value_snapshot' => $package->value,
                    'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
                    'status' => GroceryStatus::PendingVerification,
                ],
            );
            if ($redemption->balance_hold_id === null) {
                $hold = $ledger->createHold($customer, $redemption, (int) $redemption->value_snapshot, 'grocery:'.$redemption->id.':hold');
                $redemption->forceFill(['balance_hold_id' => $hold->id])->save();
            }
            if (! StatusHistory::query()->where('subject_type', GroceryRedemption::class)->where('subject_id', $redemption->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => GroceryRedemption::class, 'subject_id' => $redemption->id, 'old_status' => null, 'new_status' => GroceryStatus::PendingVerification->value, 'actor_id' => $customer->id, 'reason' => 'Warga mengajukan penukaran dari saldo.', 'occurred_at' => $now->subDays(4 - $index)]);
            }
            if ($targetStatus === GroceryStatus::PendingVerification || $redemption->status !== GroceryStatus::PendingVerification) {
                continue;
            }

            $redemption->forceFill([
                'status' => GroceryStatus::Approved,
                'approver_id' => $admin->id,
                'approved_at' => $now->subDay(),
                'availability_note' => 'Isi paket tersedia untuk disiapkan.',
                'expires_at' => $now->addDays(7),
            ])->save();
            $redemption->statusHistory()->create(['old_status' => GroceryStatus::PendingVerification->value, 'new_status' => GroceryStatus::Approved->value, 'actor_id' => $admin->id, 'reason' => 'Ketersediaan paket dikonfirmasi.', 'occurred_at' => $now->subHours(20)]);

            if ($targetStatus === GroceryStatus::Approved) {
                continue;
            }

            $staffMember = $staff[$index % 4];
            $redemption->forceFill(['status' => GroceryStatus::Preparing, 'prepared_by_id' => $staffMember->id, 'prepared_at' => $now->subHours(12)])->save();
            $redemption->statusHistory()->create(['old_status' => GroceryStatus::Approved->value, 'new_status' => GroceryStatus::Preparing->value, 'actor_id' => $staffMember->id, 'reason' => 'Petugas mulai menyiapkan paket.', 'occurred_at' => $now->subHours(12)]);

            if ($targetStatus === GroceryStatus::Preparing) {
                continue;
            }

            $redemption->forceFill(['status' => GroceryStatus::ReadyForPickup, 'ready_at' => $now->subHours(2)])->save();
            $redemption->statusHistory()->create(['old_status' => GroceryStatus::Preparing->value, 'new_status' => GroceryStatus::ReadyForPickup->value, 'actor_id' => $staffMember->id, 'reason' => 'Paket siap diserahkan kepada warga.', 'occurred_at' => $now->subHours(2)]);
        }
    }

    /** @param list<Rt> $rts
     * @param  array{categories: list<WasteCategory>, types: list<WasteType>, conditions: list<WasteCondition>}  $master
     */
    private function seedPrograms(User $admin, array $rts, array $master, CarbonImmutable $now): void
    {
        $target = CollectionTarget::query()->firstOrCreate(
            ['target_number' => 'TGT-SH-MINGGU-01'],
            [
                'name' => 'Target pengumpulan minggu ini',
                'purpose' => 'Mendorong partisipasi warga dalam pemilahan dan setoran sampah minggu ini.',
                'period_start' => $now->subDays(6)->toDateString(),
                'period_end' => $now->addDays(8)->toDateString(),
                'target_weight_kg' => '250.000',
                'status' => TargetStatus::Active,
                'is_public' => true,
                'public_min_subjects' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
            ],
        );
        if (! $target->scopes()->exists()) {
            TargetScope::query()->create(['collection_target_id' => $target->id, 'waste_category_id' => $master['categories'][0]->id]);
            TargetScope::query()->create(['collection_target_id' => $target->id, 'waste_category_id' => $master['categories'][1]->id]);
        }
        $internal = CollectionTarget::query()->firstOrCreate(
            ['target_number' => 'TGT-SH-RT-01'],
            [
                'name' => 'Target pengumpulan RT 01',
                'purpose' => 'Target internal pengumpulan material terpilah di wilayah layanan.',
                'period_start' => $now->subDays(6)->toDateString(),
                'period_end' => $now->addDays(8)->toDateString(),
                'target_weight_kg' => '80.000',
                'status' => TargetStatus::Active,
                'is_public' => false,
                'public_min_subjects' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
            ],
        );
        if (! $internal->scopes()->exists()) {
            TargetScope::query()->create(['collection_target_id' => $internal->id, 'waste_type_id' => $master['types'][0]->id, 'rt_id' => $rts[0]->id]);
        }
    }

    /** @param list<Rt> $rts */
    private function seedAnnouncements(User $admin, array $rts, CarbonImmutable $now): void
    {
        $announcement = Announcement::query()->firstOrCreate(
            ['announcement_number' => 'ANN-SH-MINGGU-01'],
            [
                'title' => 'Jadwal layanan bank sampah minggu ini',
                'body' => '<p>Layanan keliling hadir di beberapa titik selama minggu ini. Siapkan sampah yang sudah dipilah dan bawa kartu nasabah saat transaksi.</p>',
                'audience' => AnnouncementAudience::Public,
                'publish_start' => $now->subDays(6),
                'publish_end' => $now->addDays(7),
                'status' => AnnouncementStatus::Published,
                'priority' => 10,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now->subDays(6),
            ],
        );
        $announcement->rts()->syncWithoutDetaching(array_map(static fn (Rt $rt): int => $rt->id, array_slice($rts, 0, 6)));

        Announcement::query()->firstOrCreate(
            ['announcement_number' => 'ANN-SH-INTERNAL-01'],
            [
                'title' => 'Briefing petugas layanan',
                'body' => '<p>Pastikan bukti transaksi dan persetujuan warga tercatat sebelum proses diselesaikan.</p>',
                'audience' => AnnouncementAudience::Internal,
                'publish_start' => $now->subDays(2),
                'publish_end' => $now->addDays(5),
                'status' => AnnouncementStatus::Published,
                'priority' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now->subDays(2),
            ],
        );
    }

    private function seedStatisticPublication(User $admin): void
    {
        StatisticPublication::query()->updateOrCreate(
            ['publication_key' => 'public-dashboard'],
            [
                'metrics' => ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'target_progress_kg', 'mobile_service_count'],
                'dimensions' => ['period'],
                'privacy_threshold' => 5,
                'is_active' => true,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );
    }
}
