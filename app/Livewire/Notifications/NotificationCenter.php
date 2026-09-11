<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class NotificationCenter extends Component
{
    public function markAsRead(int $notificationId): void
    {
        $notifications = $this->notificationsFor($this->authenticatedUser())->whereKey($notificationId);

        $marked = (clone $notifications)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        abort_unless($marked === 1 || $notifications->exists(), 403);
    }

    public function render(): View
    {
        $actor = $this->authenticatedUser();
        $notifications = $this->notificationsFor($actor)
            ->latest('created_at')
            ->get()
            ->map(fn (Notification $notification): array => $this->notificationForDisplay($actor, $notification));

        $view = view('livewire.notifications.notification-center', [
            'notifications' => $notifications,
        ]);

        if ($actor->roles()->whereIn('name', ['petugas', 'bendahara', 'admin', 'superadmin'])->exists()) {
            $view->layout('layouts.officer');
        }

        return $view;
    }

    /** @return Builder<Notification> */
    private function notificationsFor(User $actor): Builder
    {
        return Notification::query()->where('recipient_id', $actor->getKey());
    }

    /** @return array{id: int, title: string, body: string, read: bool, reference: ?string} */
    private function notificationForDisplay(User $actor, Notification $notification): array
    {
        $contentIsApproved = $notification->isAllowlisted();

        return [
            'id' => (int) $notification->getKey(),
            'title' => $contentIsApproved ? $notification->title : 'Notifikasi baru',
            'body' => $contentIsApproved ? $notification->body : 'Detail notifikasi tidak dapat ditampilkan.',
            'read' => $notification->read_at !== null,
            'reference' => $this->authorizedReference($actor, $notification->reference),
        ];
    }

    private function authorizedReference(User $actor, ?string $reference): ?string
    {
        if (! is_string($reference) || ! $this->isSafeReference($reference)) {
            return null;
        }

        if ($this->isConcreteReference($reference)) {
            return $this->ownsConcreteReference($actor, $reference) ? $reference : null;
        }

        return Gate::forUser($actor)->allows('view-notification-reference', [$reference]) ? $reference : null;
    }

    private function isSafeReference(string $reference): bool
    {
        return preg_match('/^\/(?!\/)[A-Za-z][A-Za-z0-9_\/-]*$/', $reference) === 1;
    }

    private function isConcreteReference(string $reference): bool
    {
        return preg_match('#^/warga/(?:penjemputan|pencairan|sembako|setoran)/\d+(?:/bukti)?$#', $reference) === 1;
    }

    private function ownsConcreteReference(User $actor, string $reference): bool
    {
        $match = [];
        if (preg_match('#^/warga/penjemputan/(\d+)$#', $reference, $match) === 1) {
            return PickupRequest::query()->whereKey((int) $match[1])->where('customer_id', $actor->id)->exists();
        }
        if (preg_match('#^/warga/pencairan/(\d+)(?:/bukti)?$#', $reference, $match) === 1) {
            return WithdrawalRequest::query()->whereKey((int) $match[1])->where('customer_id', $actor->id)->exists();
        }
        if (preg_match('#^/warga/sembako/(\d+)(?:/bukti)?$#', $reference, $match) === 1) {
            return GroceryRedemption::query()->whereKey((int) $match[1])->where('customer_id', $actor->id)->exists();
        }
        if (preg_match('#^/warga/setoran/(\d+)$#', $reference, $match) === 1) {
            return Deposit::query()->whereKey((int) $match[1])->where('customer_id', $actor->id)->exists();
        }

        return false;
    }

    private function authenticatedUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
