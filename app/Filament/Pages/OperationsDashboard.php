<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditRetentionPreview;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Domain\Operations\Services\OperationalSettingsService;
use App\Domain\Operations\Services\PickupPhotoRetentionPreview;
use App\Domain\Operations\Services\PickupPhotoRetentionService;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public string $retentionPreviewBefore = '';

    #[Locked]
    public string $retentionPreviewedAt = '';

    #[Locked]
    public string $auditPreviewToken = '';

    public string $mediaRetentionBefore = '';

    public string $mediaRetentionResult = '';

    #[Locked]
    public string $mediaRetentionPreviewBefore = '';

    #[Locked]
    public string $mediaRetentionPreviewedAt = '';

    #[Locked]
    public string $mediaPreviewToken = '';

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
        $this->assertPageOperation(__FUNCTION__);
        try {
            $values = [
                'queue_backlog_threshold' => $this->settings['queue_backlog_threshold'] ?? 0,
                'backup_max_age_hours' => $this->settings['backup_max_age_hours'] ?? 0,
            ];
            if (array_key_exists('withdrawal_minimum_amount', $this->settings)) {
                $values['withdrawal_minimum_amount'] = $this->settings['withdrawal_minimum_amount'];
            }
            app(OperationalSettingsService::class)->update($this->actor(), $values);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Pengaturan tidak valid.';
            $fieldErrors = [];

            foreach (array_keys($values) as $key) {
                if ($this->invalidSettingValue($this->settings[$key] ?? null, $key === 'withdrawal_minimum_amount' ? 1_000_000 : 8_760, $key === 'withdrawal_minimum_amount' ? 5_000 : 1)) {
                    $fieldErrors['settings.'.$key] = $message;
                }
            }

            if ($fieldErrors === []) {
                throw $exception;
            }

            throw ValidationException::withMessages($fieldErrors);
        }

        $this->settings = app(OperationalSettingsService::class)->values();
    }

    public function toggleMaintenance(): void
    {
        $this->assertPageOperation(__FUNCTION__);
        if (trim($this->maintenanceReason) === '') {
            throw ValidationException::withMessages(['maintenanceReason' => 'Alasan perubahan wajib diisi.']);
        }
        try {
            app(OperationalSettingsService::class)->setMaintenance($this->actor(), ! $this->maintenanceEnabled, $this->maintenanceReason);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'maintenanceReason' => collect($exception->errors())->flatten()->first() ?? 'Alasan perubahan tidak valid.',
            ]);
        }
        $this->maintenanceEnabled = app()->maintenanceMode()->active();
        $this->maintenanceReason = '';
        $this->dispatch('close-dialog', id: 'maintenance-dialog');
    }

    public function previewRetention(): void
    {
        $this->assertPageOperation(__FUNCTION__);
        $preview = app(AuditRetentionService::class)->preview($this->actor(), $this->retentionBefore);
        $this->rememberPreview('audit', $this->retentionBefore, $preview);
        $this->retentionPreviewBefore = $this->retentionBefore;
        $this->retentionPreviewedAt = now()->toIso8601String();
        $this->retentionResult = sprintf('Batch berikutnya: %d dari %d; dilindungi: %d.', count($preview->candidateIds), $preview->deletableCount, $preview->protectedCount);
    }

    public function executeRetention(): void
    {
        $this->assertPageOperation(__FUNCTION__);
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

        $count = Cache::lock($this->previewKey('audit').':execution', 60)->block(1, function (): int {
            $ids = $this->consumePreview('audit', $this->retentionBefore, app(AuditRetentionService::class)->preview($this->actor(), $this->retentionBefore));

            return app(AuditRetentionService::class)->execute($this->actor(), $this->retentionBefore, expectedCandidates: $ids);
        });
        $this->retentionResult = sprintf('Retensi selesai. Baris audit dihapus: %d.', $count);
        $this->retentionPreviewBefore = '';
        $this->retentionPreviewedAt = '';
        $this->dispatch('close-dialog', id: 'audit-retention-dialog');
    }

    public function previewMediaRetention(): void
    {
        $this->assertPageOperation(__FUNCTION__);
        $preview = app(PickupPhotoRetentionService::class)->preview($this->actor(), $this->mediaRetentionBefore);
        $this->rememberPreview('media', $this->mediaRetentionBefore, $preview);
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
        $this->assertPageOperation(__FUNCTION__);
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

        $result = Cache::lock($this->previewKey('media').':execution', 60)->block(1, function () {
            $ids = $this->consumePreview('media', $this->mediaRetentionBefore, app(PickupPhotoRetentionService::class)->preview($this->actor(), $this->mediaRetentionBefore));

            return app(PickupPhotoRetentionService::class)->execute($this->actor(), $this->mediaRetentionBefore, $this->correlationId(), expectedCandidates: $ids);
        });
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
        $this->dispatch('close-dialog', id: 'media-retention-dialog');
    }

    public function recordBackupMetadata(): void
    {
        $this->assertPageOperation(__FUNCTION__);
        $backup = app(BackupLifecycleService::class)->request(new BackupRequest(
            actor: $this->actor(),
            artifacts: new BackupArtifactPair(
                database: new BackupArtifact($this->backupDatabaseAlias, $this->backupDatabaseSha256, $this->positiveInteger($this->backupDatabaseSizeBytes, 'Ukuran basis data', 'backupDatabaseSizeBytes')),
                media: new BackupArtifact($this->backupMediaAlias, $this->backupMediaSha256, $this->positiveInteger($this->backupMediaSizeBytes, 'Ukuran media', 'backupMediaSizeBytes')),
            ),
            retentionUntil: $this->parseRetentionUntil(),
            operatorKey: $this->backupOperatorKey,
            correlationId: $this->correlationId(),
        ));

        session()->flash('operations_notice', 'Data cadangan #'.$backup->getKey().' tercatat.');
        $this->resetBackupFields();
        $this->dispatch('close-dialog', id: 'backup-metadata-dialog');
    }

    public function recordRestoreVerification(): void
    {
        $this->assertPageOperation(__FUNCTION__);
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

        session()->flash('operations_notice', 'Hasil verifikasi pemulihan tercatat.');
        $this->restoreBackupId = '';
        $this->restoreTargetAlias = '';
        $this->restoreEvidenceReference = '';
        $this->restoreResult = '';
        $this->dispatch('close-dialog', id: 'restore-verification-dialog');
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

    private function invalidSettingValue(mixed $value, int $maximum = 8_760, int $minimum = 1): bool
    {
        if (! is_int($value) && (! is_string($value) || ! ctype_digit($value))) {
            return true;
        }

        return (int) $value < $minimum || (int) $value > $maximum;
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

    private function positiveInteger(string $value, string $label, string $property): int
    {
        if (! ctype_digit($value) || (int) $value < 1) {
            throw ValidationException::withMessages([$property => $label.' harus bilangan bulat positif.']);
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
        $actor = User::query()->findOrFail(auth()->id());

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

    private function assertPageOperation(string $operation): void
    {
        $allowed = match (static::class) {
            self::class => ['saveSettings', 'toggleMaintenance', 'previewRetention', 'executeRetention', 'previewMediaRetention', 'executeMediaRetention', 'recordBackupMetadata', 'recordRestoreVerification'],
            TechnicalSettingsPage::class => ['saveSettings'],
            TechnicalMaintenancePage::class => ['toggleMaintenance'],
            TechnicalAuditRetentionPage::class => ['previewRetention', 'executeRetention'],
            TechnicalMediaRetentionPage::class => ['previewMediaRetention', 'executeMediaRetention'],
            TechnicalBackupsPage::class => ['recordBackupMetadata', 'recordRestoreVerification'],
            default => [],
        };
        abort_unless(in_array($operation, $allowed, true), 403);
    }

    private function previewKey(string $kind): string
    {
        return 'operations-preview:v2:'.hash('sha256', static::class.':'.$this->getId().':'.auth()->id().':'.$kind);
    }

    private function rememberPreview(string $kind, string $cutoff, AuditRetentionPreview|PickupPhotoRetentionPreview $preview): void
    {
        $token = (string) Str::uuid();
        $kind === 'audit' ? $this->auditPreviewToken = $token : $this->mediaPreviewToken = $token;
        Cache::put($this->previewKey($kind), ['cutoff' => $cutoff, 'token' => $token, 'fingerprint' => hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR))], now()->addMinutes(10));
    }

    /** @return list<int> */
    private function consumePreview(string $kind, string $cutoff, AuditRetentionPreview|PickupPhotoRetentionPreview $preview): array
    {
        $record = Cache::pull($this->previewKey($kind));
        $token = $kind === 'audit' ? $this->auditPreviewToken : $this->mediaPreviewToken;
        if (! is_array($record) || ($record['token'] ?? null) !== $token || ($record['cutoff'] ?? null) !== $cutoff || ($record['fingerprint'] ?? null) !== hash('sha256', json_encode($preview, JSON_THROW_ON_ERROR))) {
            throw ValidationException::withMessages([$kind === 'audit' ? 'retentionBefore' : 'mediaRetentionBefore' => 'Pratinjau belum tersedia, sudah kedaluwarsa, atau kandidat berubah. Jalankan pratinjau kembali.']);
        }

        return $preview instanceof AuditRetentionPreview ? $preview->candidateIds : array_column($preview->items, 'id');
    }
}
