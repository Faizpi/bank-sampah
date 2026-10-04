<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageGroceryPackages
{
    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
    ) {}

    public function create(User $actor, string $code, string $name, string $contents, int $value, ?string $activeFrom = null, ?string $activeUntil = null): GroceryPackage
    {
        $this->authorize($actor);
        $attributes = $this->attributes($code, $name, $contents, $value, $activeFrom, $activeUntil);

        return DB::transaction(function () use ($actor, $attributes): GroceryPackage {
            $package = GroceryPackage::query()->create([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'contents' => $attributes['contents'],
                'value' => $attributes['value'],
                'active_from' => $attributes['active_from'],
                'active_until' => $attributes['active_until'],
                'status' => 'aktif',
            ]);
            $this->auditLogger->record($actor, 'grocery.package.created', $package, [], $this->auditValues($package), $this->correlationId());

            return $package;
        });
    }

    public function update(User $actor, GroceryPackage $package, string $code, string $name, string $contents, int $value, ?string $activeFrom = null, ?string $activeUntil = null): GroceryPackage
    {
        $this->authorize($actor);
        $attributes = $this->attributes($code, $name, $contents, $value, $activeFrom, $activeUntil);

        return DB::transaction(function () use ($actor, $package, $attributes): GroceryPackage {
            $locked = GroceryPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            $old = $this->auditValues($locked);
            $locked->forceFill($attributes)->save();
            $this->auditLogger->record($actor, 'grocery.package.updated', $locked, $old, $this->auditValues($locked), $this->correlationId());

            return $locked->fresh();
        });
    }

    public function deactivate(User $actor, GroceryPackage $package): GroceryPackage
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $package): GroceryPackage {
            $locked = GroceryPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'nonaktif') {
                return $locked;
            }
            $locked->forceFill(['status' => 'nonaktif'])->save();
            $this->auditLogger->record($actor, 'grocery.package.deactivated', $locked, ['status' => 'aktif'], ['status' => 'nonaktif'], $this->correlationId());

            return $locked->fresh();
        });
    }

    public function activate(User $actor, GroceryPackage $package): GroceryPackage
    {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $package): GroceryPackage {
            $locked = GroceryPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'aktif') {
                return $locked;
            }
            $locked->forceFill(['status' => 'aktif'])->save();
            $this->auditLogger->record($actor, 'grocery.package.activated', $locked, ['status' => 'nonaktif'], ['status' => 'aktif'], $this->correlationId());

            return $locked->fresh();
        });
    }

    /** @return array{code: string, name: string, contents: string, value: int, active_from: string|null, active_until: string|null} */
    private function attributes(string $code, string $name, string $contents, int $value, ?string $activeFrom, ?string $activeUntil): array
    {
        $code = trim($code);
        $name = trim($name);
        $contents = trim($contents);
        if (preg_match('/^[A-Za-z0-9_-]{2,40}$/', $code) !== 1) {
            throw ValidationException::withMessages(['code' => 'Kode paket tidak valid.']);
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['name' => 'Nama paket harus 2–120 karakter.']);
        }
        if (mb_strlen($contents) < 3 || mb_strlen($contents) > 1000) {
            throw ValidationException::withMessages(['contents' => 'Isi paket harus 3–1000 karakter.']);
        }
        if ($value <= 0) {
            throw ValidationException::withMessages(['value' => 'Nilai paket harus berupa rupiah integer positif.']);
        }
        $from = $this->date($activeFrom, 'active_from');
        $until = $this->date($activeUntil, 'active_until');
        if ($from !== null && $until !== null && $until->isBefore($from)) {
            throw ValidationException::withMessages(['active_until' => 'Periode aktif paket tidak valid.']);
        }

        return ['code' => $code, 'name' => $name, 'contents' => $contents, 'value' => $value, 'active_from' => $from?->toDateString(), 'active_until' => $until?->toDateString()];
    }

    private function date(?string $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta');
        if ($date === null || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => 'Tanggal harus berformat YYYY-MM-DD.']);
        }

        return $date;
    }

    /** @return array<string, mixed> */
    private function auditValues(GroceryPackage $package): array
    {
        return ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value, 'status' => $package->status];
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'grocery.package.manage')) {
            throw new AuthorizationException('Pengelolaan paket sembako memerlukan permission khusus.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
