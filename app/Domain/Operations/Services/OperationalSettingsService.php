<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Operations\Models\OperationalSetting;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class OperationalSettingsService
{
    /** @var array<string, string> */
    private const ALLOWED_SETTINGS = [
        'queue_backlog_threshold' => 'operations.health.queue_backlog_threshold',
        'backup_max_age_hours' => 'operations.health.backup_max_age_hours',
    ];

    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
        private MaintenanceMode $maintenance,
    ) {}

    /** @param array<string, mixed> $values */
    public function update(User $actor, array $values): void
    {
        $this->authorize($actor, 'system.settings.manage');
        if (! Schema::hasTable('settings')) {
            throw ValidationException::withMessages(['settings' => 'Pengaturan belum tersedia.']);
        }
        $unknown = array_diff(array_keys($values), array_keys(self::ALLOWED_SETTINGS));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['settings' => 'Pengaturan tidak diizinkan.']);
        }

        $normalized = [];
        foreach ($values as $key => $value) {
            if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
                throw ValidationException::withMessages(['settings' => 'Nilai pengaturan harus bilangan bulat positif.']);
            }
            $value = (int) $value;
            if ($value < 1 || $value > 8_760) {
                throw ValidationException::withMessages(['settings' => 'Nilai pengaturan berada di luar batas.']);
            }
            $normalized[$key] = $value;
        }

        $old = $this->values();
        DB::transaction(function () use ($actor, $normalized, $old): void {
            foreach ($normalized as $key => $value) {
                OperationalSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value, 'value_type' => 'integer', 'group' => 'operations', 'updated_by' => $actor->id],
                );
            }

            $this->auditLogger->record($actor, 'system.settings.updated', $actor, $old, $normalized, $this->correlationId());
        });

        foreach ($normalized as $key => $value) {
            config()->set(self::ALLOWED_SETTINGS[$key], $value);
        }
    }

    public function setMaintenance(User $actor, bool $enabled, string $reason): void
    {
        $this->authorize($actor, 'system.maintenance');
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1_000) {
            throw ValidationException::withMessages(['reason' => 'Alasan maintenance harus 10–1000 karakter.']);
        }

        $old = $this->maintenance->active();
        if ($old === $enabled) {
            return;
        }
        $oldData = $old ? $this->maintenance->data() : null;

        try {
            if ($enabled) {
                $this->maintenance->activate([
                    'except' => ['/operations/health', '/health'],
                    'redirect' => null,
                    'retry' => null,
                    'refresh' => null,
                    'secret' => null,
                    'status' => 503,
                    'template' => null,
                ]);
            } else {
                $this->maintenance->deactivate();
            }

            DB::transaction(function () use ($actor, $old, $enabled, $reason): void {
                $this->auditLogger->record(
                    $actor,
                    'system.maintenance.changed',
                    $actor,
                    ['enabled' => $old],
                    ['enabled' => $enabled, 'reason' => $reason],
                    $this->correlationId(),
                );
            });
        } catch (\Throwable $exception) {
            if ($old && is_array($oldData)) {
                $this->maintenance->activate($oldData);
            } else {
                $this->maintenance->deactivate();
            }

            throw $exception;
        }
    }

    /** @return array{queue_backlog_threshold: int, backup_max_age_hours: int} */
    public function values(): array
    {
        $values = [
            'queue_backlog_threshold' => (int) config(self::ALLOWED_SETTINGS['queue_backlog_threshold'], 100),
            'backup_max_age_hours' => (int) config(self::ALLOWED_SETTINGS['backup_max_age_hours'], 24),
        ];
        if (! Schema::hasTable('settings')) {
            return $values;
        }

        foreach (OperationalSetting::query()->whereIn('key', array_keys(self::ALLOWED_SETTINGS))->get(['key', 'value']) as $setting) {
            if (array_key_exists($setting->key, $values) && ctype_digit((string) $setting->value)) {
                $values[$setting->key] = (int) $setting->value;
                config()->set(self::ALLOWED_SETTINGS[$setting->key], $values[$setting->key]);
            }
        }

        return $values;
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki izin teknis.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
