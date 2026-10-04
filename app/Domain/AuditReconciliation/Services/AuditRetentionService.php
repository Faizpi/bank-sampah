<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Authorization\PermissionChecker;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuditRetentionService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

    public function execute(User $actor, string $before): int
    {
        if (! $this->permissions->allows($actor, 'audit.retention.execute')) {
            throw new AuthorizationException('Anda tidak memiliki akses retensi audit.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $before) !== 1) {
            throw ValidationException::withMessages(['before' => 'Batas retensi tidak valid.']);
        }
        $count = 0;
        DB::transaction(function () use ($actor, $before, &$count): void {
            $protected = ['reconciliation.created', 'reconciliation.approved', 'reconciliation.rejected', 'reconciliation.discrepancy.resolved', 'report.export.requested', 'report.export.completed', 'report.export.downloaded', 'audit.retention.executed'];
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

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
