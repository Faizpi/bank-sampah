<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Services\TargetService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\GroceryRedemptionResource;
use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use App\Filament\Resources\MobileServices\Models\MobileServices\MobileServiceResource;
use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Filament\Resources\Withdrawals\Models\WithdrawalRequests\WithdrawalRequestResource;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

final class WorkQueueDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pusat kerja';

    protected static ?string $title = 'Pusat kerja';

    protected string $view = 'filament.backoffice.work-queue-dashboard';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'backoffice.access');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $today = today('Asia/Jakarta');
        $now = now('Asia/Jakarta');
        $expiryHorizon = $now->copy()->addDays(2);
        $permissions = app(PermissionChecker::class);
        $actor = $this->actor();
        $queues = [];

        if ($permissions->allows($actor, 'user.verify')) {
            $queues[] = [
                'label' => 'Verifikasi warga',
                'count' => app(VisibleUsers::class)->queryFor($actor, UserStatus::PendingVerification)->whereHas('customerProfile')->count(),
                'description' => 'Periksa identitas dan aktifkan akun.',
                'cta' => 'Periksa warga',
                'href' => CitizenVerificationResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'pickup.view')) {
            $pickupQuery = app(PickupService::class)->visibleFor($actor);
            $queues[] = [
                'label' => 'Pickup hari ini',
                'count' => (clone $pickupQuery)->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])->where(function ($query) use ($today): void {
                    $query->whereDate('scheduled_date', $today)->orWhereDate('selected_date', $today);
                })->count(),
                'description' => 'Pantau penugasan dan keterlambatan.',
                'cta' => 'Pantau pickup',
                'href' => PickupRequestResource::getUrl('index'),
            ];
            $queues[] = [
                'label' => 'Pickup terlambat',
                'count' => (clone $pickupQuery)->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])->where(function ($query) use ($today): void {
                    $query->whereDate('scheduled_date', '<', $today)->orWhere(function ($query) use ($today): void {
                        $query->whereNull('scheduled_date')->whereDate('selected_date', '<', $today);
                    });
                })->count(),
                'description' => 'Tindak lanjuti tugas yang melewati jadwal.',
                'cta' => 'Tinjau pickup',
                'href' => PickupRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'withdrawal.approve') && $permissions->allows($actor, 'withdrawal.view')) {
            $withdrawalQuery = app(WithdrawalService::class)->visibleFor($actor);
            $queues[] = [
                'label' => 'Pencairan menunggu keputusan',
                'count' => (clone $withdrawalQuery)->where('status', WithdrawalStatus::PendingVerification)->count(),
                'description' => 'Periksa bukti dan dana yang ditahan.',
                'cta' => 'Tinjau pencairan',
                'href' => WithdrawalRequestResource::getUrl('index', ['filters' => ['status' => ['value' => WithdrawalStatus::PendingVerification->value]]]),
            ];
            $queues[] = [
                'label' => 'Pencairan belum ditugaskan',
                'count' => (clone $withdrawalQuery)->where('status', WithdrawalStatus::Approved)->whereNull('payer_id')->count(),
                'description' => 'Tetapkan petugas pembayaran untuk pencairan.',
                'cta' => 'Tetapkan petugas',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'withdrawal.view')) {
            $withdrawalQuery ??= app(WithdrawalService::class)->visibleFor($actor);
            $queues[] = [
                'label' => 'Segera kedaluwarsa',
                'count' => (clone $withdrawalQuery)->whereIn('status', [WithdrawalStatus::Approved, WithdrawalStatus::ReadyForPickup])->whereBetween('expires_at', [$now, $expiryHorizon])->count(),
                'description' => 'Selesaikan dalam 2 hari.',
                'cta' => 'Lihat batas waktu',
                'href' => WithdrawalRequestResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'grocery.approve') && $permissions->allows($actor, 'grocery.view')) {
            $groceryQuery = app(GroceryService::class)->visibleFor($actor);
            $queues[] = [
                'label' => 'Penukaran sembako',
                'count' => (clone $groceryQuery)->where('status', GroceryStatus::PendingVerification)->count(),
                'description' => 'Periksa ketersediaan dan setujui pengajuan.',
                'cta' => 'Tinjau sembako',
                'href' => GroceryRedemptionResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'mobile-service.operate') && $permissions->allows($actor, 'mobile-service.view')) {
            $queues[] = [
                'label' => 'Layanan menunggu dibuka',
                'count' => MobileService::query()->where('status', MobileServiceStatus::Published)->where('starts_at', '<=', $now->copy()->addDay())->count(),
                'description' => 'Buka titik layanan yang jadwalnya sudah dekat.',
                'cta' => 'Tinjau layanan',
                'href' => MobileServiceResource::getUrl('index'),
            ];
            $queues[] = [
                'label' => 'Layanan perlu ditutup',
                'count' => MobileService::query()->where('status', MobileServiceStatus::Open)->where('ends_at', '<=', $now)->count(),
                'description' => 'Tutup titik yang sudah melewati jadwal.',
                'cta' => 'Tinjau layanan',
                'href' => MobileServiceResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'target.publish') && $permissions->allows($actor, 'target.view')) {
            $targetQuery = app(TargetService::class)->visibleQuery($actor);
            $queues[] = [
                'label' => 'Target menunggu terbit',
                'count' => (clone $targetQuery)->where('status', TargetStatus::Draft)->count(),
                'description' => 'Tinjau target draf sebelum dipublikasikan.',
                'cta' => 'Tinjau target',
                'href' => CollectionTargetResource::getUrl('index'),
            ];
        }

        if ($permissions->allows($actor, 'announcement.manage') && $permissions->allows($actor, 'announcement.publish')) {
            $announcementQuery = app(AnnouncementService::class)->visibleQuery($actor);
            $queues[] = [
                'label' => 'Pengumuman menunggu terbit',
                'count' => (clone $announcementQuery)->where('status', AnnouncementStatus::Draft)->count(),
                'description' => 'Tinjau isi dan periode sebelum ditampilkan.',
                'cta' => 'Tinjau pengumuman',
                'href' => AnnouncementResource::getUrl('index'),
            ];
        }

        return [
            'queues' => $queues,
            'hasVisibleQueues' => $queues !== [],
            'environment' => app()->environment(),
            'maintenanceEnabled' => app()->maintenanceMode()->active(),
            'lastUpdated' => now('Asia/Jakarta')->translatedFormat('d F Y, H:i'),
            'activeAreas' => $permissions->allows($actor, 'region.view')
                ? ServiceArea::query()->where('is_active', true)->count()
                : null,
        ];
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
