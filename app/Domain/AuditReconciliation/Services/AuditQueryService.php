<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final readonly class AuditQueryService
{
    /** @var list<string> */
    private const FILTERS = ['action', 'actor_id', 'auditable_type', 'start', 'end', 'correlation_id'];

    public function __construct(private PermissionChecker $permissions) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function query(User $actor, array $filters): Builder
    {
        return $this->scoped($actor, $filters)->orderByDesc('occurred_at')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, AuditLog>
     */
    public function paginate(User $actor, array $filters, int $perPage = 50): LengthAwarePaginator
    {
        return $this->query($actor, $filters)->paginate($perPage);
    }

    public function find(User $actor, int $id): AuditLog
    {
        $audit = $this->scoped($actor, [])->whereKey($id)->first();
        if ($audit === null) {
            abort(404);
        }

        return $audit;
    }

    /** @return array{event_uuid: string, actor_type: string, actor_id: int|null, action: string, auditable_type: string, auditable_id: int, old_values: array<string, mixed>|null, new_values: array<string, mixed>|null, correlation_id: string, occurred_at: string} */
    public function sanitized(User $actor, int $id): array
    {
        $audit = $this->find($actor, $id);

        return ['event_uuid' => (string) $audit->event_uuid, 'actor_type' => (string) $audit->actor_type, 'actor_id' => $audit->actor_id, 'action' => (string) $audit->action, 'auditable_type' => (string) $audit->auditable_type, 'auditable_id' => (int) $audit->auditable_id, 'old_values' => $audit->old_values, 'new_values' => $audit->new_values, 'correlation_id' => (string) $audit->correlation_id, 'occurred_at' => $audit->occurred_at->setTimezone('Asia/Jakarta')->toIso8601String()];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    private function scoped(User $actor, array $filters): Builder
    {
        $this->authorize($actor);
        if (array_diff(array_keys($filters), self::FILTERS) !== [] || (isset($filters['action']) && (! is_string($filters['action']) || strlen($filters['action']) > 120)) || (isset($filters['correlation_id']) && (! is_string($filters['correlation_id']) || strlen($filters['correlation_id']) > 80))) {
            throw ValidationException::withMessages(['audit' => 'Filter audit tidak diizinkan.']);
        }
        $query = AuditLog::query();
        if (! $this->isTechnicalViewer($actor)) {
            $query->whereNotIn('action', ['system.secret.read', 'system.maintenance.changed']);
        }
        if (isset($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        foreach (['actor_id', 'auditable_type'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['correlation_id'])) {
            $query->where('correlation_id', $filters['correlation_id']);
        }
        if (isset($filters['start'], $filters['end'])) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['start']) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['end']) !== 1 || (string) $filters['start'] >= (string) $filters['end']) {
                throw ValidationException::withMessages(['period' => 'Periode audit tidak valid.']);
            }
            $query->where('occurred_at', '>=', CarbonImmutable::parse((string) $filters['start'], 'Asia/Jakarta')->startOfDay())->where('occurred_at', '<', CarbonImmutable::parse((string) $filters['end'], 'Asia/Jakarta')->startOfDay());
        }

        return $query;
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'audit.view')) {
            throw new AuthorizationException('Anda tidak memiliki akses audit.');
        }
    }

    private function isTechnicalViewer(User $actor): bool
    {
        return $actor->roles()->where('name', 'superadmin')->exists();
    }
}
