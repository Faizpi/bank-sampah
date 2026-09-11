<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Authorization\PermissionChecker;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuditRetentionService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

    public function preview(User $actor, string $before): AuditRetentionPreview
    {
        $this->authorize($actor);
        $before = $this->validatedBefore($before);
        $protected = $this->protectedActions();
        $query = DB::table('audit_logs')->where('occurred_at', '<', $before.' 00:00:00');

        return new AuditRetentionPreview(
            before: $before,
            deletableCount: (clone $query)->whereNotIn('action', $protected)->count(),
            protectedCount: (clone $query)->whereIn('action', $protected)->count(),
        );
    }

    public function execute(User $actor, string $before, bool $dryRun = false): int
    {
        $this->authorize($actor);
        $before = $this->validatedBefore($before);
        if ($dryRun) {
            return $this->preview($actor, $before)->deletableCount;
        }
        $count = 0;
        DB::transaction(function () use ($actor, $before, &$count): void {
            $protected = $this->protectedActions();
            $token = (string) Str::uuid();
            DB::table('audit_retention_context')->insert(['token' => $token]);
            $query = DB::table('audit_logs')->where('occurred_at', '<', $before.' 00:00:00')->whereNotIn('action', $protected);
            $count = $query->count();
            $query->whereRaw('? = ?', [$token, $token])->delete();
            $this->auditLogger->record($actor, 'audit.retention.executed', $actor, [], ['deleted_count' => $count, 'before' => $before], $this->correlationId());
            DB::table('audit_retention_context')->where('token', $token)->delete();
        });

        return $count;
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'audit.retention.execute')) {
            throw new AuthorizationException('Anda tidak memiliki akses retensi audit.');
        }
    }

    /** @return list<string> */
    private function protectedActions(): array
    {
        return ['report.export.requested', 'report.export.completed', 'report.export.downloaded', 'audit.retention.executed', 'media.pickup_photo_retention.executed'];
    }

    private function validatedBefore(string $before): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $before) !== 1 || CarbonImmutable::createFromFormat('!Y-m-d', $before, 'Asia/Jakarta') === null) {
            throw ValidationException::withMessages(['before' => 'Batas retensi tidak valid.']);
        }

        return $before;
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
