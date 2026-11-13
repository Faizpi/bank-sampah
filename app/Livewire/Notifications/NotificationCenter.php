<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Payouts\Models\SandboxPayout;
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

        if ($actor->hasRole('petugas', 'bendahara', 'admin')) {
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
        return preg_match('#^/warga/(?:penjemputan|pencairan|pencairan-digital|sembako|setoran)/(?:[0-9]+|[A-Za-z][A-Za-z0-9_]*-[A-Za-z0-9_-]+)(?:/bukti)?$#', $reference) === 1;
    }

    private function ownsConcreteReference(User $actor, string $reference): bool
    {
        if (preg_match('#^/warga/(penjemputan|pencairan|pencairan-digital|sembako|setoran)/([A-Za-z0-9_-]+)(?:/bukti)?$#', $reference, $match) !== 1) {
            return false;
        }
        $model = match ($match[1]) {
            'penjemputan' => new PickupRequest,
            'pencairan' => new WithdrawalRequest,
            'pencairan-digital' => new SandboxPayout,
            'sembako' => new GroceryRedemption,
            'setoran' => new Deposit,
        };
        $column = ctype_digit($match[2]) ? $model->getKeyName() : ($model instanceof Deposit ? 'deposit_number' : 'request_number');

        return $model->newQuery()->where($column, $match[2])->where('customer_id', $actor->id)->exists();
    }

    private function authenticatedUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
