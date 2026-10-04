<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

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

        return view('livewire.notifications.notification-center', [
            'notifications' => $notifications,
        ]);
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

        return Gate::forUser($actor)->allows('view-notification-reference', [$reference]) ? $reference : null;
    }

    private function isSafeReference(string $reference): bool
    {
        return preg_match('/^\/(?!\/)[A-Za-z][A-Za-z\/-]*$/', $reference) === 1
            && preg_match('/(?:^|\/)\d+(?:\/|$)/', $reference) !== 1;
    }

    private function authenticatedUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
