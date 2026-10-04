<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Models\DatabaseSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final readonly class RevokeDatabaseSession
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(User $actor, User $subject, string $sessionKey, string $correlationId): void
    {
        DB::transaction(function () use ($actor, $subject, $sessionKey, $correlationId): void {
            /** @var User $lockedSubject */
            $lockedSubject = User::query()->lockForUpdate()->findOrFail($subject->getKey());

            Gate::forUser($actor)->authorize('view', $lockedSubject);
            Gate::forUser($actor)->authorize('revokeSession', $lockedSubject);

            $session = DatabaseSession::query()
                ->whereKey($sessionKey)
                ->where('user_id', $lockedSubject->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $session->delete();

            $this->auditLogger->record(
                $actor,
                'identity.session.revoked',
                $lockedSubject,
                [],
                ['target_session_revoked' => true],
                $this->normalizedCorrelationId($correlationId),
            );
        });
    }

    private function normalizedCorrelationId(string $correlationId): string
    {
        return Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
