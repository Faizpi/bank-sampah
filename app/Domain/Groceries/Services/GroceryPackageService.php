<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final readonly class GroceryPackageService
{
    public function __construct(private PermissionChecker $permissions) {}

    /** @return Builder<GroceryPackage> */
    public function activeFor(User $actor): Builder
    {
        $this->authorize($actor, 'grocery.package.view');
        $today = today('Asia/Jakarta')->toDateString();

        return GroceryPackage::query()
            ->where('status', 'aktif')
            ->where(static function (Builder $query) use ($today): void {
                $query->whereNull('active_from')->orWhere('active_from', '<=', $today);
            })
            ->where(static function (Builder $query) use ($today): void {
                $query->whereNull('active_until')->orWhere('active_until', '>=', $today);
            })
            ->with('media')
            ->orderBy('name');
    }

    public function findActive(int $id): GroceryPackage
    {
        if ($id < 1) {
            throw ValidationException::withMessages(['package_id' => 'Paket sembako tidak valid.']);
        }
        $package = GroceryPackage::query()->whereKey($id)->first();
        $today = CarbonImmutable::now('Asia/Jakarta');
        if ($package === null || ! $package->isAvailableOn($today)) {
            throw ValidationException::withMessages(['package_id' => 'Paket sembako tidak aktif atau tidak tersedia.']);
        }

        return $package;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk melihat paket sembako.');
        }
    }
}
