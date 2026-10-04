<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Listeners\PersistNotification;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Policies\DusunPolicy;
use App\Policies\GroceryPackagePolicy;
use App\Policies\GroceryRedemptionPolicy;
use App\Policies\PickupCapacityPolicy;
use App\Policies\PickupRequestPolicy;
use App\Policies\RtPolicy;
use App\Policies\RwPolicy;
use App\Policies\ServiceAreaPolicy;
use App\Policies\WasteCategoryPolicy;
use App\Policies\WasteConditionPolicy;
use App\Policies\WastePricePolicy;
use App\Policies\WasteTypePolicy;
use App\Policies\WasteUnitPolicy;
use App\Policies\WithdrawalRequestPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Dusun::class, DusunPolicy::class);
        Gate::policy(Rw::class, RwPolicy::class);
        Gate::policy(Rt::class, RtPolicy::class);
        Gate::policy(ServiceArea::class, ServiceAreaPolicy::class);
        Gate::policy(WasteCategory::class, WasteCategoryPolicy::class);
        Gate::policy(WasteUnit::class, WasteUnitPolicy::class);
        Gate::policy(WasteCondition::class, WasteConditionPolicy::class);
        Gate::policy(WasteType::class, WasteTypePolicy::class);
        Gate::policy(WastePrice::class, WastePricePolicy::class);
        Gate::policy(PickupCapacity::class, PickupCapacityPolicy::class);
        Gate::policy(PickupRequest::class, PickupRequestPolicy::class);
        Gate::policy(WithdrawalRequest::class, WithdrawalRequestPolicy::class);
        Gate::policy(GroceryPackage::class, GroceryPackagePolicy::class);
        Gate::policy(GroceryRedemption::class, GroceryRedemptionPolicy::class);

        // A notification reference is only rendered when it points at an existing,
        // navigable route within the application. The reference string is already
        // sanitised by NotificationCenter, and the recipient is already authenticated.
        Gate::define('view-notification-reference', static function (User $actor, string $reference): bool {
            $path = parse_url($reference, PHP_URL_PATH);

            if (! is_string($path) || $path === '' || str_starts_with($path, '//')) {
                return false;
            }

            try {
                Route::getRoutes()->match(Request::create($path, 'GET'));
            } catch (NotFoundHttpException) {
                return false;
            }

            return true;
        });

        Event::listen(NotificationRequested::class, PersistNotification::class);

        RateLimiter::for('registration', static fn (Request $request): Limit => Limit::perMinute((int) config('app.registration_max_attempts_per_minute'))
            ->by((string) $request->ip()));
        RateLimiter::for('public-qr', static fn (Request $request): Limit => Limit::perMinute((int) config('app.public_qr_max_attempts_per_minute'))
            ->by(hash('sha256', (string) $request->ip().'|'.substr((string) $request->route('token'), 0, 12))));
        RateLimiter::for('public-data', static fn (Request $request): Limit => Limit::perMinute((int) config('app.public_data_max_attempts_per_minute'))
            ->by((string) $request->ip()));
        RateLimiter::for('uploads', static fn (Request $request): Limit => Limit::perMinute((int) config('app.upload_max_attempts_per_minute'))
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('exports', static fn (Request $request): Limit => Limit::perMinute((int) config('app.export_max_attempts_per_minute'))
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('financial', static fn (Request $request): Limit => Limit::perMinute((int) config('app.financial_request_max_attempts_per_minute'))
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        config()->set('livewire.temporary_file_upload.middleware', 'throttle:uploads');
        Livewire::addPersistentMiddleware(ThrottleRequests::class);
    }
}
