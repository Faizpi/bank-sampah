<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;

it('defines exactly the persisted account statuses', function (): void {
    expect(array_column(UserStatus::cases(), 'value'))->toBe([
        'menunggu_verifikasi',
        'aktif',
        'ditolak',
        'nonaktif',
    ]);

    foreach (UserStatus::cases() as $status) {
        expect(UserStatus::from($status->value))->toBe($status);
    }
});

it('rejects unknown persisted statuses', function (): void {
    UserStatus::from('unknown');
})->throws(ValueError::class);
