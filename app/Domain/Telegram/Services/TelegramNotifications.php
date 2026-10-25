<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

final class TelegramNotifications
{
    public function produce(): void
    {
        if (! TelegramAccess::configured()) {
            return;
        }
        TelegramLink::query()->whereNull('blocked_at')->chunkById(50, function ($links): void {
            foreach ($links as $link) {
                try {
                    $actor = app(TelegramAccess::class)->actor($link);
                } catch (AuthorizationException) {
                    continue;
                }
                // Recovery uses the same key as the after-commit listener, not a second notification.
                if ($link->transaction_notifications) {
                    foreach (Notification::query()->where('recipient_id', $actor->id)->where('created_at', '>=', $link->created_at)->orderByDesc('id')->limit(50)->get() as $notice) {
                        if (! $notice->isAllowlisted()) {
                            continue;
                        }
                        app(QueueTelegramNotification::class)->enqueue($link, $notice->title, $notice->body, $notice->reference, $notice->dedupe_key, $notice->scheduled_at);
                    }
                    $this->newTasks($link, $actor);
                }
                if ($link->reminders) {
                    $this->remind($link, $actor);
                }
                if ($link->summaries && $actor->hasRole('warga')) {
                    $this->summary($link, $actor);
                }
                if ($link->announcements) {
                    $this->announcements($link);
                }
            }
        });
    }

    /** @return array<string, Builder<PickupRequest>|Builder<WithdrawalRequest>|Builder<GroceryRedemption>> */
    private function activeQueries(User $actor, bool $waiting): array
    {
        if ($actor->hasRole('warga')) {
            return [
                'pickup' => app(PickupService::class)->visibleFor($actor)->whereIn('status', ['menunggu_petugas', 'dijadwalkan', 'menuju_lokasi', 'dijemput'])->whereDate('selected_date', '<=', today()),
                'cash' => app(WithdrawalService::class)->visibleFor($actor)->whereIn('status', ['menunggu_bendahara', 'diproses_bendahara'])->whereDate('pickup_date', '<=', today()),
                'grocery' => app(GroceryService::class)->visibleFor($actor)->whereIn('status', ['menunggu_petugas', 'diproses_petugas', 'siap_diambil']),
            ];
        }
        if ($actor->hasRole('bendahara')) {
            $query = app(WithdrawalService::class)->payableFor($actor);
            $waiting ? $query->whereNull('payer_id') : $query->where('payer_id', $actor->id);

            return ['cash' => $query];
        }
        $pickup = app(PickupService::class)->visibleFor($actor)->whereIn('status', $waiting ? ['menunggu_petugas'] : ['dijadwalkan', 'menuju_lokasi', 'dijemput']);
        $grocery = app(GroceryService::class)->readyForHandover($actor);
        if ($waiting) {
            $grocery->whereNull('claimed_by_id');
        } else {
            $pickup->where('assigned_staff_id', $actor->id);
            $grocery->where('claimed_by_id', $actor->id);
        }

        return ['pickup' => $pickup, 'grocery' => $grocery];
    }

    private function newTasks(TelegramLink $link, User $actor): void
    {
        if ($actor->hasRole('warga')) {
            return;
        }
        foreach ($this->activeQueries($actor, true) as $kind => $query) {
            foreach ($query->orderByDesc('id')->limit(5)->get() as $row) {
                app(TelegramUi::class)->queue($link->telegram_id, $link, 'task:'.$link->id.':'.$kind.':'.$row->id, ['kind' => 'text', 'text' => 'Tugas baru: '.$row->request_number.'. Buka /menu untuk memeriksa dan mengambil tugas.', 'guards' => [['kind' => $kind, 'id' => $row->id, 'status' => $row->status->value]]], 'transaction_notifications');
            }
        }
    }

    private function remind(TelegramLink $link, User $actor): void
    {
        if (now('Asia/Jakarta')->hour !== (int) config('telegram.reminder_hour')) {
            return;
        }
        if (TelegramOutbox::query()->where('link_id', $link->id)->where('category', 'reminders')->where('created_at', '>', now()->subHours((int) config('telegram.reminder_hours')))->exists()) {
            return;
        }
        $guards = [];
        foreach ($this->activeQueries($actor, false) as $kind => $query) {
            foreach ($query->where('created_at', '<=', now()->subHours((int) config('telegram.reminder_hours')))->limit(5)->get() as $row) {
                $guards[] = ['kind' => $kind, 'id' => $row->id, 'status' => $row->status->value];
            }
        }
        if ($guards !== []) {
            app(TelegramUi::class)->queue($link->telegram_id, $link, 'reminder:'.$link->id.':'.today()->format('Ymd'), ['kind' => 'text', 'text' => 'Masih ada pengajuan/tugas yang menunggu tindakan. Buka /menu untuk melihat status terbaru. Jangan ulangi penyerahan fisik yang sudah dilakukan.', 'guards' => $guards], 'reminders');
        }
    }

    private function summary(TelegramLink $link, User $actor): void
    {
        if ($this->withinQuietHours() || now('Asia/Jakarta')->hour < 9 || ($link->summary_frequency === 'weekly' && ! today()->isMonday()) || ($link->summary_frequency === 'monthly' && today()->day !== 1)) {
            return;
        }
        $period = match ($link->summary_frequency) {
            'weekly' => today()->format('oW'), 'monthly' => today()->format('Ym'), default => today()->format('Ymd')
        };
        app(TelegramUi::class)->queue($link->telegram_id, $link, 'summary:'.$link->id.':'.$link->summary_frequency.':'.$period, ['kind' => 'text', 'text' => 'Ringkasan tabungan per '.now()->translatedFormat('d M Y H:i')."\n".app(CitizenTelegramBot::class)->balance($actor)], 'summaries');
    }

    private function announcements(TelegramLink $link): void
    {
        if ($this->withinQuietHours()) {
            return;
        }
        $ids = app(AnnouncementService::class)->publicQuery()->where('published_at', '>=', $link->created_at)->limit(5)->pluck('id')->all();
        if ($ids !== []) {
            app(TelegramUi::class)->queue($link->telegram_id, $link, 'announcements:'.$link->id.':'.today()->format('Ymd'), ['kind' => 'announcements', 'ids' => $ids], 'announcements');
        }
    }

    /** Scheduled digests stay silent between 20:00 and 07:00 WIB. */
    private function withinQuietHours(): bool
    {
        $hour = (int) now('Asia/Jakarta')->hour;

        return $hour >= 20 || $hour < 7;
    }
}
