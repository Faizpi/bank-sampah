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
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupItem;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Models\StatusHistory;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Shared\Weight;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
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
 * a neutral prefix and every lookup is idempotent, so re-running db:seed is safe.
 */
final class LocalDataSeeder extends Seeder
{
    /** @var list<string> */
    private const CUSTOMER_NAMES = [
        'Asep Saepuloh', 'Ujang Suherman', 'Nia Kurniasih',
        'Fajar Nugraha', 'Yani Mulyani', 'Lilis Suryani', 'Rudi Hartono',
        'Euis Komariah', 'Budi Santoso',
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
        $staff = $this->seedStaff($regions['areas'], $passwordHash, $now);
        $customers = $this->seedCustomers($regions['rts'], $passwordHash, $now);
        $master = WasteMasterSeeder::seed($admin);
        $master['categories'] = array_values($master['categories']);
        $prices = $master['prices'];
        $pickups = $this->seedPickups($regions, $staff, $customers, $master['types'], $now);

        DB::transaction(function () use ($admin, $customers, $staff, $master, $prices, $pickups, $regions, $now): void {
            $ledger = app(LedgerService::class);
            foreach ($customers as $customer) {
                $ledger->ensureAccount($customer);
            }

            $this->seedDeposits($customers, $staff, $master['types'], $master['conditions'], $prices, $pickups, $ledger, $now);
            $this->seedWithdrawals($admin, $customers, $staff, $ledger, $now);
            // Paket sembako dan penukaran tidak lagi dibuat oleh seeder demo;
            // data sembako historical di production tidak pernah dihapus oleh seeder.
            $this->seedPrograms($admin, $regions['rts'], $master, $now);
            $this->seedAnnouncements($admin, $regions['rts'], $now);
            $this->seedStatisticPublication($admin);
        });

        $this->command->info('Data lokal siap: '.count($customers).' warga, '.count($regions['rts']).' RT, '.count($master['types']).' jenis sampah, dan histori transaksi 30 hari.');
    }

    /** @return array{dusuns: list<Dusun>, rws: list<Rw>, rts: list<Rt>, areas: list<ServiceArea>} */
    private function seedRegions(User $admin): array
    {
        $manager = app(ManageRegions::class);
        $dusuns = [];
        $rws = [];
        $rts = [];

        foreach ([
            ['code' => 'DSN-BS-BINAAN', 'name' => 'Dusun Binaan'],
        ] as $dusunData) {
            $dusun = Dusun::query()->where('code', $dusunData['code'])->first();
            $dusun ??= $manager->createDusun($admin, $dusunData['code'], $dusunData['name']);
            $dusuns[] = $dusun;

            foreach (range(1, 2) as $rwNumber) {
                $rwCode = $dusunData['code'].'-RW-'.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT);
                $rw = Rw::query()->where('dusun_id', $dusun->id)->where('code', $rwCode)->first();
                $rw ??= $manager->createRw($admin, $dusun, $rwCode, 'RW '.str_pad((string) $rwNumber, 2, '0', STR_PAD_LEFT).' '.$dusun->name);
                $rws[] = $rw;

                foreach (range(1, 2) as $rtNumber) {
                    $rtCode = $rwCode.'-RT-'.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT);
                    $rt = Rt::query()->where('rw_id', $rw->id)->where('code', $rtCode)->first();
                    $rt ??= $manager->createRt($admin, $rw, $rtCode, 'RT '.str_pad((string) $rtNumber, 2, '0', STR_PAD_LEFT).' '.$rw->name);
                    $rts[] = $rt;
                }
            }
        }

        self::deactivateNonCanonicalRegions($admin, $rts, $rws, $dusuns);

        $areaNames = ['Layanan Binaan RW 01', 'Layanan Binaan RW 02'];
        $areas = [];
        foreach (array_chunk($rts, 2) as $index => $areaRts) {
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
    private function seedStaff(array $areas, string $passwordHash, CarbonImmutable $now): array
    {
        $activeFrom = $now->subDays(30)->toDateString();

        $petugas = User::query()->where('email', DeveloperUsersSeeder::email('petugas'))->firstOrFail();
        $petugasProfile = StaffProfile::query()->updateOrCreate(
            ['user_id' => $petugas->id],
            ['staff_number' => 'STF-BS-001', 'service_area_id' => $areas[0]->id, 'active_from' => $activeFrom, 'active_to' => null],
        );
        $this->syncActiveAreaAssignments($petugasProfile, $areas, $activeFrom);

        $bendahara = User::query()->where('email', DeveloperUsersSeeder::email('bendahara'))->firstOrFail();
        $treasurerProfile = StaffProfile::query()->updateOrCreate(
            ['user_id' => $bendahara->id],
            ['staff_number' => 'STF-BS-002', 'service_area_id' => $areas[0]->id, 'active_from' => $activeFrom, 'active_to' => null],
        );
        $this->syncActiveAreaAssignments($treasurerProfile, $areas, $activeFrom);
        $this->deactivateLegacyStaff($now);

        return [$petugas, $bendahara];
    }

    private function deactivateLegacyStaff(CarbonImmutable $now): void
    {
        $canonicalIds = [
            User::query()->where('email', DeveloperUsersSeeder::email('petugas'))->value('id'),
            User::query()->where('email', DeveloperUsersSeeder::email('bendahara'))->value('id'),
        ];

        $legacy = User::query()
            ->whereHas('staffProfile')
            ->whereNotIn('id', array_filter($canonicalIds))
            ->whereHas('roles', static fn ($query) => $query->whereIn('name', ['petugas', 'bendahara']))
            ->get();

        foreach ($legacy as $user) {
            $user->roles()->detach(Role::query()->whereIn('name', ['petugas', 'bendahara'])->pluck('id')->all());
            $profile = $user->staffProfile()->first();
            if (! $profile instanceof StaffProfile) {
                continue;
            }

            $profile->forceFill(['active_to' => $now->subDay()->toDateString()])->save();
            StaffServiceArea::query()
                ->where('staff_profile_user_id', $profile->user_id)
                ->whereNull('active_to')
                ->update(['active_to' => $now->subDay()->toDateString()]);
        }
    }

    /** @param list<ServiceArea> $areas */
    private function syncActiveAreaAssignments(StaffProfile $profile, array $areas, string $activeFrom): void
    {
        foreach ($areas as $area) {
            StaffServiceArea::query()->updateOrCreate(
                ['staff_profile_user_id' => $profile->user_id, 'service_area_id' => $area->id],
                ['active_from' => $activeFrom, 'active_to' => null],
            );
        }
    }

    /**
     * Soft-deactivate any region that is not part of the canonical demo set.
     * History (deposits, customers) is preserved; only `is_active` flips.
     *
     * @param  list<Rt>  $rts
     * @param  list<Rw>  $rws
     * @param  list<Dusun>  $dusuns
     */
    private static function deactivateNonCanonicalRegions(User $admin, array $rts, array $rws, array $dusuns): void
    {
        $manager = app(ManageRegions::class);
        $rtIds = array_map(static fn (Rt $rt): int => (int) $rt->id, $rts);
        $rwIds = array_map(static fn (Rw $rw): int => (int) $rw->id, $rws);
        $dusunIds = array_map(static fn (Dusun $dusun): int => (int) $dusun->id, $dusuns);
        $today = CarbonImmutable::now('Asia/Jakarta');

        $staleAreas = ServiceArea::query()
            ->whereNotIn('name', ['Layanan Binaan RW 01', 'Layanan Binaan RW 02'])
            ->where('is_active', true)
            ->get();
        $staleAreas->each(function (ServiceArea $area) use ($manager, $admin): void {
            $manager->deactivate($admin, $area);
        });
        if ($staleAreas->isNotEmpty()) {
            StaffServiceArea::query()
                ->whereIn('service_area_id', $staleAreas->pluck('id'))
                ->whereNull('active_to')
                ->update(['active_to' => $today->subDay()->toDateString()]);
        }

        Rt::query()->whereNotIn('id', $rtIds)->where('is_active', true)->get()
            ->each(function (Rt $rt) use ($manager, $admin): void {
                $manager->deactivate($admin, $rt);
            });
        Rw::query()->whereNotIn('id', $rwIds)->where('is_active', true)->get()
            ->each(function (Rw $rw) use ($manager, $admin): void {
                $manager->deactivate($admin, $rw);
            });
        Dusun::query()->whereNotIn('id', $dusunIds)->where('is_active', true)->get()
            ->each(function (Dusun $dusun) use ($manager, $admin): void {
                $manager->deactivate($admin, $dusun);
            });
    }

    private function fixtureId(string $type, CarbonImmutable $now, int $sequence): string
    {
        return sprintf('%s-BS-%s-%03d', $type, $now->format('Ym'), $sequence);
    }

    /** @param list<Rt> $rts
     * @return list<User>
     */
    private function seedCustomers(array $rts, string $passwordHash, CarbonImmutable $now): array
    {
        $role = Role::query()->where('name', 'warga')->firstOrFail();
        $shortcutCustomer = User::query()->where('email', DeveloperUsersSeeder::email('warga'))->firstOrFail();
        $shortcutCustomer->customerProfile()->update([
            'rt_id' => $rts[0]->id,
            'address' => 'Kampung Cikadu, '.$rts[0]->name.', Desa Binaan',
            'joined_at' => $now->subDays(30)->toDateString(),
        ]);
        $customers = [$shortcutCustomer];
        $addresses = ['Kampung Cikadu', 'Kampung Babakan', 'Kampung Pasirhuni', 'Kampung Sukamaju', 'Kampung Cibogo', 'Kampung Kiarapyaung'];
        foreach (self::CUSTOMER_NAMES as $index => $name) {
            $number = $index + 2;
            $user = User::query()->updateOrCreate(
                ['phone' => '628140000'.str_pad((string) $number, 4, '0', STR_PAD_LEFT)],
                [
                    'name' => $name,
                    'email' => 'warga.'.str_pad((string) $number, 3, '0', STR_PAD_LEFT).'@example.test',
                    'email_verified_at' => now(),
                    'password' => $passwordHash,
                    'status' => UserStatus::Active,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Data awal']]);
            $profile = CustomerProfile::query()->firstOrNew(['user_id' => $user->id]);
            // Regenerate when either half of the token pair is missing: a hash
            // without its stored plaintext can never be rendered again, so the
            // whole pair is replaced. Complete pairs survive re-seeding.
            $needsToken = $profile->qr_token_hash === null || $profile->qr_token_encrypted === null;
            $token = $needsToken ? QrToken::generate() : null;
            $profile->forceFill([
                'customer_number' => $profile->customer_number ?? 'CST-'.str_pad((string) $number, 8, '0', STR_PAD_LEFT),
                'rt_id' => $rts[$index % count($rts)]->id,
                'address' => $addresses[$index % count($addresses)].', '.$rts[$index % count($rts)]->name.', Desa Binaan',
                'joined_at' => $now->subDays(29 - ($index % 30))->toDateString(),
                'qr_token_hash' => $needsToken ? $token?->hash() : $profile->qr_token_hash,
                'qr_token_encrypted' => $needsToken ? $token?->value() : $profile->qr_token_encrypted,
                'qr_rotated_at' => $profile->qr_rotated_at ?? now(),
            ])->save();
            $customers[] = $user;
        }

        return $customers;
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
        foreach (range(1, 5) as $number) {
            $areaIndex = ($number - 1) % count($regions['areas']);
            $area = $regions['areas'][$areaIndex];
            $customer = collect($customers)->first(function (User $candidate) use ($area): bool {
                $rtId = $candidate->customerProfile()->value('rt_id');

                return $rtId !== null && $area->rts()->whereKey($rtId)->exists();
            });
            $customer ??= $customers[($number * 3) % count($customers)];
            $rt = $customer->customerProfile()->firstOrFail()->rt()->firstOrFail();
            $date = match ($number) {
                3 => $now->toDateString(),
                4 => $now->addDay()->toDateString(),
                default => $now->subDays(3 - $number)->toDateString(),
            };
            $status = match ($number) {
                1, 2 => PickupStatus::Completed,
                3 => PickupStatus::Scheduled,
                4 => PickupStatus::PendingReview,
                default => PickupStatus::Rejected,
            };
            $pickup = PickupRequest::query()->firstOrCreate(
                ['request_number' => $this->fixtureId('PUP', $now, $number)],
                [
                    'customer_id' => $customer->id,
                    'rt_id' => $rt->id,
                    'service_area_id' => $area->id,
                    'address' => (string) $customer->customerProfile()->firstOrFail()->address,
                    'selected_date' => $date,
                    'scheduled_date' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $date : null,
                    'estimated_weight_kg' => number_format(4.5 + ($number * 0.8), 3, '.', ''),
                    'notes' => 'Warga mengajukan penjemputan sampah terpilah.',
                    'status' => $status,
                    'rejection_reason' => $status === PickupStatus::Rejected ? 'Alamat belum dapat dijangkau pada jadwal yang dipilih.' : null,
                    'cancellation_reason' => null,
                    'assigned_staff_id' => in_array($status, [PickupStatus::PendingReview, PickupStatus::Rejected], true) ? null : $staff[$areaIndex % count($staff)]->id,
                    'accepted_at' => in_array($status, [PickupStatus::Accepted, PickupStatus::Scheduled, PickupStatus::Completed], true) ? $now->subDays(max(1, 3 - $number))->setTime(9, 0) : null,
                    'scheduled_at' => in_array($status, [PickupStatus::Scheduled, PickupStatus::Completed], true) ? $now->subDays(max(1, 3 - $number))->setTime(10, 0) : null,
                    'en_route_at' => $status === PickupStatus::Completed ? $now->subDays(3 - $number)->setTime(11, 0) : null,
                    'picked_up_at' => $status === PickupStatus::Completed ? $now->subDays(3 - $number)->setTime(11, 30) : null,
                    'completed_at' => $status === PickupStatus::Completed ? $now->subDays(3 - $number)->setTime(12, 0) : null,
                ],
            );
            foreach (array_slice($types, 0, 2) as $typeIndex => $type) {
                PickupItem::query()->firstOrCreate(
                    ['pickup_request_id' => $pickup->id, 'waste_type_id' => $type->id],
                    ['estimated_weight_kg' => number_format(2 + ($number * 0.25) + $typeIndex, 3, '.', ''), 'estimated_quantity' => 3 + $number],
                );
            }
            if (! StatusHistory::query()->where('subject_type', PickupRequest::class)->where('subject_id', $pickup->id)->exists()) {
                StatusHistory::query()->create(['subject_type' => PickupRequest::class, 'subject_id' => $pickup->id, 'old_status' => null, 'new_status' => $status->value, 'actor_id' => $staff[$areaIndex % count($staff)]->id, 'reason' => 'Status awal layanan penjemputan.', 'occurred_at' => $pickup->completed_at ?? $pickup->created_at ?? $now]);
            }
            $pickups[] = $pickup;
        }

        return $pickups;
    }

    /** @param list<User> $customers
     * @param  list<User>  $staff
     * @param  list<WasteType>  $types
     * @param  list<WasteCondition>  $conditions
     * @param  array<int, array<int, WastePrice>>  $prices
     * @param  list<PickupRequest>  $pickups
     */
    private function seedDeposits(array $customers, array $staff, array $types, array $conditions, array $prices, array $pickups, LedgerService $ledger, CarbonImmutable $now): void
    {
        // 30 deposits across the last 30 days with a realistic method mix so the
        // dashboards, reports, and method breakdowns all show non-trivial data.
        foreach (range(1, 30) as $seedNumber) {
            $dayOffset = $seedNumber - 1;
            $number = $this->fixtureId('DEP', $now, $seedNumber);
            if (Deposit::query()->where('deposit_number', $number)->exists()) {
                continue;
            }

            $pickup = match ($seedNumber) {
                3 => $pickups[0] ?? null,
                12 => $pickups[1] ?? null,
                default => null,
            };

            $customer = $pickup instanceof PickupRequest
                ? User::query()->findOrFail($pickup->customer_id)
                : $customers[($seedNumber * 5) % count($customers)];
            $staffMember = $staff[($seedNumber - 1) % count($staff)];
            $occurredAt = $now->subDays($dayOffset)->setTime(8 + ($seedNumber % 8), ($seedNumber * 7) % 60);
            $method = $pickup instanceof PickupRequest ? 'penjemputan' : 'langsung';
            $token = QrToken::generate();
            $deposit = Deposit::query()->create([
                'deposit_number' => $number,
                'customer_id' => $customer->id,
                'staff_id' => $staffMember->id,
                'method' => $method,
                'pickup_request_id' => $pickup?->id,
                'location' => 'Loket Bank Sampah',
                'occurred_at' => $occurredAt,
                'status' => Deposit::STATUS_DRAFT,
            ]);

            $totalGrams = 0;
            $totalValue = 0;
            foreach ([$types[$seedNumber % count($types)], $types[($seedNumber + 2) % count($types)]] as $itemIndex => $type) {
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

    /** @param list<User> $customers
     * @param  list<User>  $staff
     */
    private function seedWithdrawals(User $admin, array $customers, array $staff, LedgerService $ledger, CarbonImmutable $now): void
    {
        // Three withdrawals cover every status the dashboards and receipt flows
        // need: one awaiting verification, one approved, and one fully paid.
        $outcomes = [
            1 => WithdrawalStatus::PendingVerification,
            2 => WithdrawalStatus::Approved,
            3 => WithdrawalStatus::Paid,
        ];

        $payer = $staff[count($staff) - 1];

        foreach ($outcomes as $number => $targetStatus) {
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
            $profile = $customer->customerProfile()->firstOrFail();
            $rt = $profile->rt()->firstOrFail();
            $area = $rt->serviceAreas()->where('is_active', true)->firstOrFail();
            $requestNumber = $this->fixtureId('WDR', $now, $number);
            $withdrawal = WithdrawalRequest::query()->firstOrCreate(
                ['request_number' => $requestNumber],
                [
                    'customer_id' => $customer->id,
                    'rt_id' => $rt->id,
                    'service_area_id' => $area->id,
                    'requested_by_id' => $customer->id,
                    'amount' => $amount,
                    'status' => WithdrawalStatus::PendingVerification,
                    'pickup_location' => 'Loket Bank Sampah',
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
            if (in_array($targetStatus, [WithdrawalStatus::Approved, WithdrawalStatus::Paid], true) && $withdrawal->status === WithdrawalStatus::PendingVerification) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::Approved, 'approver_id' => $admin->id, 'approved_at' => $now->subDays(1)])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::PendingVerification->value, 'new_status' => WithdrawalStatus::Approved->value, 'actor_id' => $admin->id, 'reason' => 'Pencairan telah diverifikasi.', 'occurred_at' => $now->subHours(18)]);
            }
            if ($targetStatus === WithdrawalStatus::Paid && $withdrawal->status === WithdrawalStatus::Approved) {
                $withdrawal->forceFill(['status' => WithdrawalStatus::ReadyForPickup, 'payer_id' => $payer->id])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::Approved->value, 'new_status' => WithdrawalStatus::ReadyForPickup->value, 'actor_id' => $payer->id, 'reason' => 'Bendahara ditetapkan sebagai petugas pembayar.', 'occurred_at' => $now->subHours(12)]);
            }
            if ($targetStatus === WithdrawalStatus::Paid && $withdrawal->status === WithdrawalStatus::ReadyForPickup) {
                $entry = $ledger->convertHold($withdrawal->balanceHold()->firstOrFail(), 'withdrawal:'.$withdrawal->id.':payment');
                $withdrawal->forceFill([
                    'status' => WithdrawalStatus::Paid,
                    'paid_at' => $now->subHour(),
                    'recipient_verification' => 'Kartu nasabah dan identitas cocok.',
                    'recipient_reference' => $profile->customer_number,
                    'receipt_ledger_entry_id' => $entry->id,
                ])->save();
                StatusHistory::query()->create(['subject_type' => WithdrawalRequest::class, 'subject_id' => $withdrawal->id, 'old_status' => WithdrawalStatus::ReadyForPickup->value, 'new_status' => WithdrawalStatus::Paid->value, 'actor_id' => $payer->id, 'reason' => 'Pencairan dibayarkan oleh bendahara.', 'occurred_at' => $now->subHour()]);
            }
        }
    }

    /** @param list<Rt> $rts
     * @param  array{categories: list<WasteCategory>, types: list<WasteType>, conditions: list<WasteCondition>}  $master
     */
    private function seedPrograms(User $admin, array $rts, array $master, CarbonImmutable $now): void
    {
        $target = CollectionTarget::query()->firstOrCreate(
            ['target_number' => $this->fixtureId('TGT', $now, 1)],
            [
                'name' => 'Target pengumpulan 30 hari layanan',
                'purpose' => 'Mendorong partisipasi warga dalam pemilahan dan setoran sampah sepanjang 30 hari operasional berikutnya.',
                'period_start' => $now->startOfDay()->toDateString(),
                'period_end' => $now->addDays(30)->endOfDay()->toDateString(),
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
            ['target_number' => $this->fixtureId('TGT', $now, 2)],
            [
                'name' => 'Target internal RT 01 periode berjalan',
                'purpose' => 'Target internal pengumpulan material terpilah selama 30 hari operasional berikutnya.',
                'period_start' => $now->startOfDay()->toDateString(),
                'period_end' => $now->addDays(30)->endOfDay()->toDateString(),
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
            ['announcement_number' => $this->fixtureId('ANN', $now, 1)],
            [
                'title' => 'Jadwal layanan bank sampah minggu ini',
                'body' => '<p>Layanan setoran hadir di beberapa titik selama minggu ini. Siapkan sampah yang sudah dipilah dan bawa kartu nasabah saat transaksi.</p>',
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
            ['announcement_number' => $this->fixtureId('ANN', $now, 2)],
            [
                'title' => 'Briefing petugas layanan',
                'body' => '<p>Pastikan bukti transaksi dan persetujuan warga tercatat sebelum proses diselesaikan.</p>',
                'audience' => AnnouncementAudience::Internal,
                'publish_start' => $now->subDays(2),
                'publish_end' => $now->addDays(30),
                'status' => AnnouncementStatus::Published,
                'priority' => 5,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now->subDays(2),
            ],
        );

        $nextMonth = Announcement::query()->firstOrCreate(
            ['announcement_number' => $this->fixtureId('ANN', $now, 3)],
            [
                'title' => 'Rencana penjemputan 30 hari',
                'body' => '<p>Jadwal penjemputan dan kapasitas layanan tersedia untuk 30 hari ke depan di seluruh wilayah layanan. Pilih tanggal yang sesuai sebelum kapasitas penuh.</p>',
                'audience' => AnnouncementAudience::Public,
                'publish_start' => $now,
                'publish_end' => $now->addDays(30),
                'status' => AnnouncementStatus::Published,
                'priority' => 8,
                'created_by' => $admin->id,
                'published_by' => $admin->id,
                'published_at' => $now,
            ],
        );
        $nextMonth->rts()->syncWithoutDetaching(array_map(static fn (Rt $rt): int => $rt->id, $rts));
    }

    private function seedStatisticPublication(User $admin): void
    {
        StatisticPublication::query()->updateOrCreate(
            ['publication_key' => 'public-dashboard'],
            [
                'metrics' => ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'target_progress_kg'],
                'dimensions' => ['period'],
                'privacy_threshold' => 5,
                'is_active' => true,
                'approved_by' => $admin->id,
                'approved_at' => now(),
            ],
        );
    }
}
