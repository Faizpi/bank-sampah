<?php

declare(strict_types=1);

use App\Domain\Payouts\Models\SandboxPayout;

it('uses the same concise withdrawal status labels on every payout surface', function (string $status, string $label): void {
    $payout = new SandboxPayout(['status' => $status]);

    expect($payout->label())->toBe($label)->not->toContain('Simulasi');
})->with([
    ['queued', 'Pencairan sedang diproses'],
    ['dispatching', 'Pencairan sedang diproses'],
    ['pending', 'Pencairan sedang diproses'],
    ['succeeded', 'Pencairan berhasil'],
    ['failed', 'Pencairan gagal'],
    ['reversed', 'Saldo pencairan dikembalikan'],
    ['cancelled', 'Pencairan dibatalkan'],
    ['uncertain', 'Status sedang diperiksa'],
]);
