<?php

declare(strict_types=1);

namespace App\Domain\Communication\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AnnouncementService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

    /** @param list<int> $rtIds */
    public function create(User $actor, string $title, string $body, string $audience, string $startsAt, ?string $endsAt, array $rtIds = [], int $priority = 0): Announcement
    {
        $this->authorize($actor, 'announcement.manage');
        $clean = $this->validate($title, $body, $audience, $startsAt, $endsAt, $rtIds, $priority);

        return DB::transaction(function () use ($actor, $clean, $rtIds): Announcement {
            $announcement = new Announcement;
            $announcement->forceFill(array_merge($clean, [
                'announcement_number' => 'ANN-'.strtoupper(Str::random(18)),
                'created_by' => $actor->id,
                'status' => AnnouncementStatus::Draft,
            ]))->save();
            $announcement->rts()->sync($rtIds);
            $this->auditLogger->record($actor, 'announcement.created', $announcement, [], $this->auditValues($announcement), $this->correlationId());

            return $announcement->fresh('rts');
        });
    }

    /** @param list<int> $rtIds */
    public function update(User $actor, Announcement $announcement, string $title, string $body, string $audience, string $startsAt, ?string $endsAt, array $rtIds = [], int $priority = 0): Announcement
    {
        $this->authorize($actor, 'announcement.manage');
        $clean = $this->validate($title, $body, $audience, $startsAt, $endsAt, $rtIds, $priority);

        return DB::transaction(function () use ($actor, $announcement, $clean, $rtIds): Announcement {
            $locked = Announcement::query()->whereKey($announcement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [AnnouncementStatus::Draft, AnnouncementStatus::Inactive], true)) {
                throw ValidationException::withMessages(['announcement' => 'Pengumuman terbit harus dinonaktifkan sebelum diubah.']);
            }
            $old = $this->auditValues($locked);
            $locked->forceFill($clean)->save();
            $locked->rts()->sync($rtIds);
            $this->auditLogger->record($actor, 'announcement.updated', $locked, $old, $this->auditValues($locked), $this->correlationId());

            return $locked->fresh('rts');
        });
    }

    public function publish(User $actor, Announcement $announcement): Announcement
    {
        $this->authorize($actor, 'announcement.publish');

        return DB::transaction(function () use ($actor, $announcement): Announcement {
            $locked = Announcement::query()->whereKey($announcement->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, [AnnouncementStatus::Draft, AnnouncementStatus::Inactive], true) || $locked->publish_end !== null && $locked->publish_end <= now()) {
                throw ValidationException::withMessages(['announcement' => 'Pengumuman tidak dapat diterbitkan pada status atau periode ini.']);
            }
            $old = $locked->status->value;
            $locked->forceFill(['status' => AnnouncementStatus::Published, 'published_by' => $actor->id, 'published_at' => now()])->save();
            $this->auditLogger->record($actor, 'announcement.published', $locked, ['status' => $old], ['status' => AnnouncementStatus::Published->value], $this->correlationId());

            return $locked->fresh('rts');
        });
    }

    public function unpublish(User $actor, Announcement $announcement): Announcement
    {
        $this->authorize($actor, 'announcement.publish');

        return DB::transaction(function () use ($actor, $announcement): Announcement {
            $locked = Announcement::query()->whereKey($announcement->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== AnnouncementStatus::Published) {
                throw ValidationException::withMessages(['announcement' => 'Hanya pengumuman terbit yang dapat dinonaktifkan.']);
            }
            $locked->forceFill(['status' => AnnouncementStatus::Inactive])->save();
            $this->auditLogger->record($actor, 'announcement.unpublished', $locked, ['status' => AnnouncementStatus::Published->value], ['status' => AnnouncementStatus::Inactive->value], $this->correlationId());

            return $locked->fresh('rts');
        });
    }

    public function canView(User $actor, Announcement $announcement): bool
    {
        return $this->visibleQuery($actor)->whereKey($announcement->getKey())->exists();
    }

    /** @return Builder<Announcement> */
    public function visibleQuery(User $actor): Builder
    {
        $canManage = $this->permissions->allows($actor, 'announcement.manage');
        if (! $canManage && ! $this->permissions->allows($actor, 'announcement.view')) {
            return Announcement::query()->whereRaw('1 = 0');
        }
        if ($canManage) {
            return Announcement::query();
        }

        $customerProfile = $actor->customerProfile()->first(['user_id', 'rt_id']);
        $hasStaffProfile = $actor->staffProfile()->exists();

        return Announcement::query()
            ->where('status', AnnouncementStatus::Published)
            ->where('publish_start', '<=', now())
            ->where(static fn (Builder $query): Builder => $query->whereNull('publish_end')->orWhere('publish_end', '>', now()))
            ->where(static function (Builder $query) use ($customerProfile, $hasStaffProfile): void {
                $query->where('audience', AnnouncementAudience::Public);
                if ($customerProfile instanceof CustomerProfile) {
                    $query->orWhere('audience', AnnouncementAudience::Citizen);
                    $query->orWhere(static fn (Builder $region): Builder => $region->where('audience', AnnouncementAudience::Region)->whereHas('rts', static fn (Builder $rts): Builder => $rts->whereKey($customerProfile->rt_id)));
                }
                if ($hasStaffProfile) {
                    $query->orWhere('audience', AnnouncementAudience::Officer);
                }
            });
    }

    /** @return Builder<Announcement> */
    public function publicQuery(): Builder
    {
        return Announcement::query()->select(['id', 'announcement_number', 'title', 'body', 'publish_start', 'publish_end', 'priority'])->where('audience', AnnouncementAudience::Public)->where('status', AnnouncementStatus::Published)->where('publish_start', '<=', now())->where(static fn (Builder $query): Builder => $query->whereNull('publish_end')->orWhere('publish_end', '>', now()))->orderByDesc('priority')->orderByDesc('publish_start');
    }

    /** @param list<int> $rtIds
     * @return array{title: string, body: string, audience: string, publish_start: CarbonImmutable, publish_end: CarbonImmutable|null, priority: int}
     */
    private function validate(string $title, string $body, string $audience, string $startsAt, ?string $endsAt, array $rtIds, int $priority): array
    {
        $title = trim($title);
        $body = trim($body);
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160 || mb_strlen($body) < 3 || mb_strlen($body) > 10000 || ! in_array($audience, array_column(AnnouncementAudience::cases(), 'value'), true) || $priority < 0 || $priority > 1000) {
            throw ValidationException::withMessages(['announcement' => 'Isi, audiens, periode, atau prioritas pengumuman tidak valid.']);
        }
        $clean = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', strip_tags($body, '<p><br><strong><em><ul><ol><li>')) ?? '';
        $start = CarbonImmutable::parse($startsAt, 'Asia/Jakarta');
        $end = $endsAt === null ? null : CarbonImmutable::parse($endsAt, 'Asia/Jakarta');
        if ($end !== null && $start >= $end) {
            throw ValidationException::withMessages(['publish_end' => 'Periode pengumuman harus berurutan.']);
        }
        if ($audience !== AnnouncementAudience::Region->value && $rtIds !== []) {
            throw ValidationException::withMessages(['rt_ids' => 'RT hanya dapat dipakai untuk audiens wilayah.']);
        }
        if ($audience === AnnouncementAudience::Region->value && $rtIds === []) {
            throw ValidationException::withMessages(['rt_ids' => 'Audiens wilayah memerlukan minimal satu RT.']);
        }
        if ($rtIds !== [] && Rt::query()->whereIn('id', $rtIds)->where('is_active', true)->count() !== count(array_unique($rtIds))) {
            throw ValidationException::withMessages(['rt_ids' => 'Wilayah pengumuman harus aktif.']);
        }

        return ['title' => $title, 'body' => $clean, 'audience' => $audience, 'publish_start' => $start, 'publish_end' => $end, 'priority' => $priority];
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap pengumuman.');
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(Announcement $announcement): array
    {
        return ['announcement_number' => $announcement->announcement_number, 'audience' => $announcement->audience->value, 'status' => $announcement->status->value];
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
