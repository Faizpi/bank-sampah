<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Actions;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\RegionModel;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ManageRegions
{
    public function createDusun(User $actor, string $code, string $name): Dusun
    {
        $this->authorize($actor, 'create', Dusun::class);

        return Dusun::query()->create(['code' => $code, 'name' => $name, 'is_active' => true]);
    }

    public function createRw(User $actor, Dusun $dusun, string $code, string $name): Rw
    {
        $this->authorize($actor, 'create', Rw::class);
        $this->ensureActive($dusun, 'dusun_id');

        return Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code, 'name' => $name, 'is_active' => true]);
    }

    public function createRt(User $actor, Rw $rw, string $code, string $name): Rt
    {
        $this->authorize($actor, 'create', Rt::class);
        $this->ensureActiveRwHierarchy($rw);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $name, 'is_active' => true]);
    }

    /** @param list<Rt> $rts */
    public function createServiceArea(User $actor, string $name, array $rts): ServiceArea
    {
        $this->authorize($actor, 'create', ServiceArea::class);
        $this->ensureServiceAreaRtsAreActive($rts);
        $area = ServiceArea::query()->create(['name' => $name, 'is_active' => true]);
        $this->syncRts($area, $rts);

        return $area;
    }

    public function updateDusun(User $actor, Dusun $dusun, string $code, string $name): void
    {
        $this->authorize($actor, 'update', $dusun);
        $this->persist($dusun, ['code' => $code, 'name' => $name]);
    }

    public function updateRw(User $actor, Rw $rw, Dusun $dusun, string $code, string $name): void
    {
        $this->authorize($actor, 'update', $rw);
        $this->ensureActive($dusun, 'dusun_id');
        $this->persist($rw, ['dusun_id' => $dusun->id, 'code' => $code, 'name' => $name]);
    }

    public function updateRt(User $actor, Rt $rt, Rw $rw, string $code, string $name): void
    {
        $this->authorize($actor, 'update', $rt);
        $this->ensureActiveRwHierarchy($rw);
        $this->persist($rt, ['rw_id' => $rw->id, 'code' => $code, 'name' => $name]);
    }

    /** @param list<Rt> $rts */
    public function updateServiceArea(User $actor, ServiceArea $area, string $name, array $rts): void
    {
        $this->authorize($actor, 'update', $area);
        $this->ensureServiceAreaRtsAreActive($rts);
        $this->persist($area, ['name' => $name]);
        $this->syncRts($area, $rts);
    }

    public function deactivate(User $actor, RegionModel $region): void
    {
        $this->authorize($actor, 'deactivate', $region);
        $this->persist($region, ['is_active' => false]);
    }

    public function activate(User $actor, RegionModel $region): void
    {
        $this->authorize($actor, 'activate', $region);

        if ($region instanceof Rw) {
            $this->ensureActive($region->dusun, 'dusun_id');
        } elseif ($region instanceof Rt) {
            $this->ensureActiveRwHierarchy($region->rw);
        } elseif ($region instanceof ServiceArea) {
            $this->ensureServiceAreaRtsAreActive($region->rts()->get()->all());
        }

        $this->persist($region, ['is_active' => true]);
    }

    /** @param array<string, mixed> $attributes */
    private function persist(RegionModel $region, array $attributes): void
    {
        RegionMutationGuard::run(fn (): bool => $region->forceFill($attributes)->save());
    }

    /** @param list<Rt> $rts */
    private function syncRts(ServiceArea $area, array $rts): void
    {
        RegionMutationGuard::run(fn () => $area->rts()->sync(array_map(fn (Rt $rt): int => $rt->id, $rts)));
    }

    /** @param list<Rt> $rts */
    private function ensureServiceAreaRtsAreActive(array $rts): void
    {
        foreach ($rts as $rt) {
            $rt = $rt->fresh(['rw.dusun']);

            if ($rt === null || ! $rt->is_active || ! $rt->rw->is_active || ! $rt->rw->dusun->is_active) {
                throw ValidationException::withMessages(['rts' => 'Every assigned RT and its administrative hierarchy must be active.']);
            }
        }
    }

    private function ensureActiveRwHierarchy(Rw $rw): void
    {
        $rw = $rw->fresh('dusun');

        if ($rw === null) {
            throw ValidationException::withMessages(['rw_id' => 'The selected parent region must exist.']);
        }

        $this->ensureActive($rw, 'rw_id');
        $this->ensureActive($rw->dusun, 'rw_id');
    }

    private function ensureActive(RegionModel $region, string $field): void
    {
        $region = $region->fresh() ?? $region;

        if (! $region->is_active) {
            throw ValidationException::withMessages([$field => 'The selected parent region must be active.']);
        }
    }

    /** @param class-string<RegionModel>|RegionModel $target */
    private function authorize(User $actor, string $ability, string|RegionModel $target): void
    {
        Gate::forUser($actor)->authorize($ability, $target);
    }
}
