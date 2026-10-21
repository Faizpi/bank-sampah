<?php

declare(strict_types=1);

namespace App\Filament;

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
use Filament\Resources\Resource as FilamentResource;

/** Only current resources may be registered, even if an upload leaves old PHP files behind. */
final class BackofficeResources
{
    /** @return list<class-string<FilamentResource>> */
    public static function all(): array
    {
        return [
            AuditLogResource::class,
            AnnouncementResource::class,
            DusunResource::class,
            RtResource::class,
            RwResource::class,
            ServiceAreaResource::class,
            DepositResource::class,
            GroceryPackageResource::class,
            GroceryRedemptionResource::class,
            CitizenVerificationResource::class,
            CustomerResource::class,
            PasswordAssistanceResource::class,
            SessionInventoryResource::class,
            UserResource::class,
            BalanceHoldResource::class,
            LedgerEntryResource::class,
            MobileServiceResource::class,
            PickupRequestResource::class,
            CollectionTargetResource::class,
            StatisticPublicationResource::class,
            WasteCategoryResource::class,
            WasteConditionResource::class,
            WastePriceResource::class,
            WasteTypeResource::class,
            WasteUnitResource::class,
            WithdrawalRequestResource::class,
        ];
    }
}
