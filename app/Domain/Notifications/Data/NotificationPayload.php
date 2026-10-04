<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Data;

use App\Domain\Notifications\Support\NotificationTemplateRegistry;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;

final readonly class NotificationPayload
{
    private const SENSITIVE_TERMS = [
        'password',
        'kata sandi',
        'token',
        'secret',
        'rahasia',
    ];

    public ?CarbonImmutable $scheduledAt;

    public function __construct(
        public int $recipientId,
        public string $type,
        public string $title,
        public string $body,
        public string $reference,
        public string $dedupeKey,
        ?DateTimeInterface $scheduledAt = null,
    ) {
        if ($recipientId < 1 || $type === '' || $title === '' || $body === '' || $reference === '') {
            throw new RuntimeException('Notification payload fields must not be empty.');
        }

        if (strlen($type) > 100 || strlen($title) > 160 || strlen($reference) > 120 || strlen($dedupeKey) > 191) {
            throw new RuntimeException('Notification payload exceeds storage limits.');
        }

        if (! in_array($type, NotificationTemplateRegistry::keys(), true)) {
            throw new RuntimeException('Notification template is not allowlisted.');
        }

        if (preg_match('/^\/(?!\/)[A-Za-z][A-Za-z0-9_\/-]*$/', $reference) !== 1) {
            throw new RuntimeException('Notification reference must be an internal path.');
        }

        $content = strtolower($title.' '.$body.' '.$reference);
        foreach (self::SENSITIVE_TERMS as $term) {
            if (str_contains($content, $term)) {
                throw new RuntimeException('Notification payload contains sensitive content.');
            }
        }

        $this->scheduledAt = $scheduledAt === null ? null : CarbonImmutable::instance($scheduledAt);
    }

    /** @return array{recipient_id: int, type: string, title: string, body: string, reference: string, dedupe_key: string, scheduled_at: ?CarbonImmutable} */
    public function toArray(): array
    {
        return [
            'recipient_id' => $this->recipientId,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'reference' => $this->reference,
            'dedupe_key' => $this->dedupeKey,
            'scheduled_at' => $this->scheduledAt,
        ];
    }
}
