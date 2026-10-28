<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Services\SendTelegramOutbox;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class TelegramSettings extends Component
{
    public string $password = '';

    #[Locked]
    public ?string $challengeId = null;

    #[Locked]
    public ?string $linkUrl = null;

    public bool $transaction_notifications = false;

    public bool $reminders = false;

    public bool $summaries = false;

    public bool $announcements = false;

    public string $summary_frequency = 'weekly';

    public function mount(TelegramAccess $access): void
    {
        abort_unless(TelegramAccess::configured(), 404);
        $access->eligible($this->actor());
        $link = TelegramLink::query()->where('user_id', $this->actor()->id)->first();
        if ($link !== null) {
            foreach (array_keys($this->preferences()) as $field) {
                $this->{$field} = $link->getAttribute($field);
            }
        }
    }

    public function begin(TelegramLinkService $links): void
    {
        try {
            $challenge = $links->begin($this->actor(), $this->password);
            $this->challengeId = $challenge['id'];
            $this->linkUrl = $challenge['url'];
        } finally {
            $this->password = '';
        }
    }

    public function approve(TelegramLinkService $links): void
    {
        abort_unless($this->challengeId !== null, 422);
        $link = $links->approve($this->actor(), $this->challengeId, $this->preferences());
        $this->reset('challengeId', 'linkUrl', 'password');
        session()->flash('telegram_success', 'Telegram berhasil ditautkan.');
        app(SendTelegramOutbox::class)->send(1, $link->telegram_id);
    }

    public function savePreferences(TelegramLinkService $links): void
    {
        $links->updatePreferences($this->actor(), $this->preferences());
        session()->flash('telegram_success', 'Pilihan notifikasi disimpan.');
    }

    public function revoke(TelegramLinkService $links): void
    {
        try {
            $links->revoke($this->actor(), $this->actor(), $this->password);
            $this->reset('challengeId', 'linkUrl');
            session()->flash('telegram_success', 'Tautan dicabut. Telegram lama tidak lagi memiliki akses akun ini.');
        } finally {
            $this->password = '';
        }
    }

    public function render(): View
    {
        $actor = $this->actor();
        $link = TelegramLink::query()->where('user_id', $actor->id)->first();

        return view('livewire.profile.telegram-settings', [
            'link' => $link,
            'needsVerification' => $link !== null && ! hash_equals($link->account_fingerprint, TelegramAccess::fingerprint($actor)),
            'challenge' => $this->challengeId === null ? null : TelegramChallenge::query()->whereKey($this->challengeId)->where('user_id', $actor->id)->whereNull('consumed_at')->whereNull('cancelled_at')->where('expires_at', '>', now())->first(),
        ])->layout($actor->hasRole('warga') ? 'layouts.citizen' : 'layouts.officer');
    }

    /** @return array<string, bool|string> */
    private function preferences(): array
    {
        return ['transaction_notifications' => $this->transaction_notifications, 'reminders' => $this->reminders, 'summaries' => $this->summaries, 'announcements' => $this->announcements, 'summary_frequency' => $this->summary_frequency];
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        app(TelegramAccess::class)->eligible($actor);

        return $actor;
    }
}
