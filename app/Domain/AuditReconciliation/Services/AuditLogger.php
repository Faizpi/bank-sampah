<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Shared\LogContextSanitizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class AuditLogger
{
    public function __construct(
        private LogContextSanitizer $sanitizer,
        private Request $request,
    ) {}

    /**
     * @param  array<array-key, mixed>  $oldValues
     * @param  array<array-key, mixed>  $newValues
     */
    public function record(
        ?Model $actor,
        string $action,
        Model $auditable,
        array $oldValues,
        array $newValues,
        string $correlationId,
    ): AuditLog {
        $audit = new AuditLog;
        $audit->forceFill([
            'event_uuid' => (string) Str::uuid(),
            'actor_type' => $actor === null ? 'system' : $this->actorType($actor),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'old_values' => $this->sanitizer->sanitize($oldValues),
            'new_values' => $this->sanitizer->sanitize($newValues),
            'ip_address_hash' => $this->ipAddressHash(),
            'user_agent' => $this->userAgent(),
            'correlation_id' => $correlationId,
            'occurred_at' => now(),
        ]);
        $audit->save();

        return $audit;
    }

    private function actorType(Model $actor): string
    {
        return $actor->getTable() === 'users' ? 'user' : $actor::class;
    }

    private function ipAddressHash(): ?string
    {
        $ipAddress = $this->request->ip();

        return $ipAddress === null ? null : hash('sha256', $ipAddress);
    }

    private function userAgent(): ?string
    {
        $userAgent = $this->request->userAgent();

        return $userAgent === null ? null : Str::limit($userAgent, 512, '');
    }
}
