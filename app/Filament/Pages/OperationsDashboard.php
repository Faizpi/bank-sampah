<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Domain\Operations\Services\OperationalSettingsService;
use App\Domain\Operations\Services\PickupPhotoRetentionService;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class OperationsDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi sistem';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Kontrol teknis';

    protected static ?string $title = 'Kontrol teknis';

    protected string $view = 'filament.backoffice.operations-dashboard';

    /** @var array<string, int> */
    public array $settings = [];

    public bool $maintenanceEnabled = false;

    public string $maintenanceReason = '';

    public string $retentionBefore = '';

    public string $retentionResult = '';

    public string $retentionPreviewBefore = '';

    public string $retentionPreviewedAt = '';

    public string $mediaRetentionBefore = '';

    public string $mediaRetentionResult = '';

    public string $mediaRetentionPreviewBefore = '';

    public string $mediaRetentionPreviewedAt = '';

    public int $mediaRetentionCandidateCount = 0;

    public string $mediaRetentionCandidateSize = '0 B';

    public int $mediaRetentionMissingFiles = 0;

    /** @var list<array{id: int, pickup_number: string, pickup_status: string, original_name: string, size: int, created_at: string, file_exists: bool}> */
    public array $mediaRetentionCandidates = [];

    public string $backupDatabaseAlias = '';

    public string $backupDatabaseSha256 = '';

    public string $backupDatabaseSizeBytes = '';

    public string $backupMediaAlias = '';

    public string $backupMediaSha256 = '';

    public string $backupMediaSizeBytes = '';

    public string $backupRetentionUntil = '';

    public string $backupOperatorKey = '';

    public string $restoreBackupId = '';

    public string $restoreTargetAlias = '';

    public string $restoreEvidenceReference = '';

    public string $restoreResult = '';

    public static function canAccess(): bool
    {
        return static::hasAnyTechnicalPermission([
            'system.settings.manage',
            'system.maintenance',
            'backup.view',
            'backup.run',
            'backup.restore',
            'audit.retention.execute',
            'media.retention.execute',
        ]);
    }

    public function mount(): void
    {
        $actor = $this->actor();
        $permissions = app(PermissionChecker::class);
        $this->settings = $permissions->allows($actor, 'system.settings.manage')
            ? app(OperationalSettingsService::class)->values()
            : [];
        $this->maintenanceEnabled = $permissions->allows($actor, 'system.maintenance')
            && app()->maintenanceMode()->active();
        $minimumAge = max(30, (int) config('operations.retention.pickup_photo_minimum_age_days', 30));
        $defaultAge = max($minimumAge, (int) config('operations.retention.pickup_photo_default_age_days', 180));
        $this->mediaRetentionBefore = now('Asia/Jakarta')->subDays($defaultAge)->toDateString();
    }

    public function saveSettings(): void
    {
        app(OperationalSettingsService::class)->update($this->actor(), [
            'queue_backlog_threshold' => $this->settings['queue_backlog_threshold'] ?? 0,
            'backup_max_age_hours' => $this->settings['backup_max_age_hours'] ?? 0,
        ]);
        $this->settings = app(OperationalSettingsService::class)->values();
    }

    public function toggleMaintenance(): void
    {
        if (trim($this->maintenanceReason) === '') {
            throw ValidationException::withMessages(['maintenanceReason' => 'Alasan perubahan wajib diisi.']);
        }
        app(OperationalSettingsService::class)->setMaintenance($this->actor(), ! $this->maintenanceEnabled, $this->maintenanceReason);
        $this->maintenanceEnabled = app()->maintenanceMode()->active();
        $this->maintenanceReason = '';
    }

    public function previewRetention(): void
    {
        $preview = app(AuditRetentionService::class)->preview($this->actor(), $this->retentionBefore);
        $this->retentionPreviewBefore = $this->retentionBefore;
        $this->retentionPreviewedAt = now()->toIso8601String();
        $this->retentionResult = sprintf('Akan dihapus: %d; dilindungi: %d.', $preview->deletableCount, $preview->protectedCount);
    }

    public function executeRetention(): void
    {
        if ($this->retentionPreviewBefore !== $this->retentionBefore || $this->retentionPreviewedAt === '') {
            throw ValidationException::withMessages(['retentionBefore' => 'Jalankan preview dengan batas tanggal yang sama sebelum retensi.']);
        }

        try {
            $previewedAt = CarbonImmutable::parse($this->retentionPreviewedAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['retentionBefore' => 'Preview retensi tidak valid. Jalankan preview kembali.']);
        }

        if ($previewedAt->addMinutes(10)->isPast()) {
            throw ValidationException::withMessages(['retentionBefore' => 'Preview retensi sudah kedaluwarsa. Jalankan preview kembali.']);
        }

        $count = app(AuditRetentionService::class)->execute($this->actor(), $this->retentionBefore);
        $this->retentionResult = sprintf('Retensi selesai. Baris audit dihapus: %d.', $count);
        $this->retentionPreviewBefore = '';
        $this->retentionPreviewedAt = '';
    }

    public function previewMediaRetention(): void
    {
        $preview = app(PickupPhotoRetentionService::class)->preview($this->actor(), $this->mediaRetentionBefore);
        $this->mediaRetentionPreviewBefore = $this->mediaRetentionBefore;
        $this->mediaRetentionPreviewedAt = now()->toIso8601String();
        $this->mediaRetentionCandidateCount = $preview->deletableCount;
        $this->mediaRetentionCandidateSize = $this->formatBytes($preview->deletableBytes);
        $this->mediaRetentionMissingFiles = $preview->batchMissingFileCount;
        $this->mediaRetentionCandidates = $preview->items;
        $this->mediaRetentionResult = $preview->deletableCount > $preview->batchCount
            ? sprintf('Pratinjau siap. Batch berikutnya memuat %d dari %d kandidat.', $preview->batchCount, $preview->deletableCount)
            : sprintf('Pratinjau siap. Kandidat yang akan diproses: %d.', $preview->batchCount);
    }

    public function executeMediaRetention(): void
    {
        if ($this->mediaRetentionPreviewBefore !== $this->mediaRetentionBefore || $this->mediaRetentionPreviewedAt === '') {
            throw ValidationException::withMessages(['mediaRetentionBefore' => 'Jalankan pratinjau dengan batas tanggal yang sama sebelum menghapus foto.']);
        }

        try {
            $previewedAt = CarbonImmutable::parse($this->mediaRetentionPreviewedAt);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['mediaRetentionBefore' => 'Pratinjau retensi foto tidak valid. Jalankan pratinjau kembali.']);
        }

        if ($previewedAt->addMinutes(10)->isPast()) {
            throw ValidationException::withMessages(['mediaRetentionBefore' => 'Pratinjau retensi foto sudah kedaluwarsa. Jalankan pratinjau kembali.']);
        }

        $result = app(PickupPhotoRetentionService::class)->execute(
            $this->actor(),
            $this->mediaRetentionBefore,
            $this->correlationId(),
        );
        $this->mediaRetentionResult = sprintf(
            'Pembersihan selesai. %d foto (%s) dihapus; %d catatan sudah kehilangan file fisik.',
            $result->deletedCount,
            $this->formatBytes($result->deletedBytes),
            $result->missingFileCount,
        );
        $this->mediaRetentionPreviewBefore = '';
        $this->mediaRetentionPreviewedAt = '';
        $this->mediaRetentionCandidateCount = 0;
        $this->mediaRetentionCandidateSize = '0 B';
        $this->mediaRetentionMissingFiles = 0;
        $this->mediaRetentionCandidates = [];
    }

    public function recordBackupMetadata(): void
    {
        $backup = app(BackupLifecycleService::class)->request(new BackupRequest(
            actor: $this->actor(),
            artifacts: new BackupArtifactPair(
                database: new BackupArtifact($this->backupDatabaseAlias, $this->backupDatabaseSha256, $this->positiveInteger($this->backupDatabaseSizeBytes, 'Ukuran basis data')),
                media: new BackupArtifact($this->backupMediaAlias, $this->backupMediaSha256, $this->positiveInteger($this->backupMediaSizeBytes, 'Ukuran media')),
            ),
            retentionUntil: $this->parseRetentionUntil(),
            operatorKey: $this->backupOperatorKey,
            correlationId: $this->correlationId(),
        ));

        session()->flash('operations_notice', 'Metadata cadangan tercatat. Penyalinan basis data dan media tetap dijalankan melalui prosedur penerapan. ID: '.$backup->getKey());
        $this->resetBackupFields();
    }

    public function recordRestoreVerification(): void
    {
        if (! in_array($this->restoreResult, ['passed', 'failed'], true)) {
            throw ValidationException::withMessages(['restoreResult' => 'Pilih hasil verifikasi: lulus atau gagal.']);
        }

        if (! ctype_digit($this->restoreBackupId) || (int) $this->restoreBackupId < 1) {
            throw ValidationException::withMessages(['restoreBackupId' => 'ID cadangan tidak valid.']);
        }

        $backup = BackupLog::query()->findOrFail((int) $this->restoreBackupId);
        app(BackupLifecycleService::class)->recordRestoreVerification(
            actor: $this->actor(),
            backup: $backup,
            verificationTargetAlias: $this->restoreTargetAlias,
            evidenceReference: $this->restoreEvidenceReference,
            passed: $this->restoreResult === 'passed',
            correlationId: $this->correlationId(),
        );

        session()->flash('operations_notice', 'Hasil verifikasi pemulihan tercatat. Aplikasi tidak menjalankan pemulihan atau menyentuh berkas cadangan.');
        $this->restoreBackupId = '';
        $this->restoreTargetAlias = '';
        $this->restoreEvidenceReference = '';
        $this->restoreResult = '';
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $actor = $this->actor();
        $permissions = app(PermissionChecker::class);
        $canInspectHealth = $permissions->allows($actor, 'system.maintenance');
        $backups = $permissions->allows($actor, 'backup.view')
            ? BackupLog::query()->select([
                'id', 'backup_pair_uuid', 'status', 'database_location_alias', 'media_location_alias',
                'database_sha256', 'media_sha256', 'database_size_bytes', 'media_size_bytes',
                'retention_until', 'started_at', 'finished_at', 'restore_tested_at', 'restore_verification_result', 'created_at',
            ])->latest('id')->limit(20)->get()
            : collect();

        return [
            'health' => $canInspectHealth ? app(OperationalHealthService::class)->check()->toArray() : [],
            'backups' => $backups,
            'canManageSettings' => $permissions->allows($actor, 'system.settings.manage'),
            'canManageMaintenance' => $permissions->allows($actor, 'system.maintenance'),
            'canRunBackup' => $permissions->allows($actor, 'backup.run'),
            'canRestoreBackup' => $permissions->allows($actor, 'backup.restore'),
            'canExecuteRetention' => $permissions->allows($actor, 'audit.retention.execute'),
            'canExecuteMediaRetention' => $permissions->allows($actor, 'media.retention.execute'),
            'canViewBackups' => $permissions->allows($actor, 'backup.view'),
            'mediaRetentionMinimumAgeDays' => max(30, (int) config('operations.retention.pickup_photo_minimum_age_days', 30)),
            'mediaRetentionBatchLimit' => min(500, max(1, (int) config('operations.retention.pickup_photo_batch_size', 100))),
            'mediaRetentionLatestCutoff' => now('Asia/Jakarta')
                ->subDays(max(30, (int) config('operations.retention.pickup_photo_minimum_age_days', 30)))
                ->toDateString(),
        ];
    }

    private function parseRetentionUntil(): CarbonImmutable
    {
        try {
            $value = CarbonImmutable::parse($this->backupRetentionUntil);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['backupRetentionUntil' => 'Retensi cadangan tidak valid.']);
        }

        if ($value->isPast()) {
            throw ValidationException::withMessages(['backupRetentionUntil' => 'Retensi cadangan harus berada di masa depan.']);
        }

        return $value;
    }

    private function positiveInteger(string $value, string $label): int
    {
        if (! ctype_digit($value) || (int) $value < 1) {
            throw ValidationException::withMessages(['backup' => $label.' harus bilangan bulat positif.']);
        }

        return (int) $value;
    }

    private function resetBackupFields(): void
    {
        $this->backupDatabaseAlias = '';
        $this->backupDatabaseSha256 = '';
        $this->backupDatabaseSizeBytes = '';
        $this->backupMediaAlias = '';
        $this->backupMediaSha256 = '';
        $this->backupMediaSizeBytes = '';
        $this->backupRetentionUntil = '';
        $this->backupOperatorKey = '';
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }

    protected static function hasTechnicalPermission(string $permission): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, $permission);
    }

    /** @param list<string> $permissions */
    protected static function hasAnyTechnicalPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (static::hasTechnicalPermission($permission)) {
                return true;
            }
        }

        return false;
    }
}
