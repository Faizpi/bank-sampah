<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

use Illuminate\Validation\ValidationException;

final class NotificationTemplateRegistry
{
    /** @var array<string, array{title: string, body: string, placeholders: list<string>}> */
    private const TEMPLATES = [
        'deposit.finalized' => ['title' => 'Setoran selesai', 'body' => 'Setoran {reference} telah selesai diproses.', 'placeholders' => ['reference']],
        'transaction.corrected' => ['title' => 'Koreksi setoran tercatat', 'body' => 'Koreksi setoran {reference} telah tercatat.', 'placeholders' => ['reference']],
        'transaction.reversed' => ['title' => 'Setoran dibalik', 'body' => 'Setoran {reference} telah diproses melalui pembalikan resmi.', 'placeholders' => ['reference']],
        'pickup.requested' => ['title' => 'Pengajuan penjemputan diterima', 'body' => 'Pengajuan penjemputan {reference} telah diterima.', 'placeholders' => ['reference']],
        'pickup.accepted' => ['title' => 'Penjemputan diterima', 'body' => 'Pengajuan penjemputan {reference} telah diterima.', 'placeholders' => ['reference']],
        'pickup.rejected' => ['title' => 'Penjemputan ditolak', 'body' => 'Pengajuan penjemputan {reference} ditolak.', 'placeholders' => ['reference']],
        'pickup.scheduled' => ['title' => 'Penjemputan dijadwalkan', 'body' => 'Penjemputan {reference} telah dijadwalkan.', 'placeholders' => ['reference']],
        'pickup.status.changed' => ['title' => 'Status penjemputan diperbarui', 'body' => 'Status penjemputan {reference} telah diperbarui.', 'placeholders' => ['reference']],
        'pickup.completed' => ['title' => 'Penjemputan selesai', 'body' => 'Penjemputan {reference} telah selesai.', 'placeholders' => ['reference']],
        'pickup.cancelled' => ['title' => 'Penjemputan dibatalkan', 'body' => 'Penjemputan {reference} telah dibatalkan.', 'placeholders' => ['reference']],
        'pickup.expired' => ['title' => 'Penjemputan kedaluwarsa', 'body' => 'Penjemputan {reference} kedaluwarsa.', 'placeholders' => ['reference']],
        'withdrawal.requested' => ['title' => 'Pengajuan pencairan diterima', 'body' => 'Pengajuan pencairan {reference} telah diterima.', 'placeholders' => ['reference']],
        'withdrawal.approved' => ['title' => 'Pencairan disetujui', 'body' => 'Pengajuan pencairan {reference} disetujui.', 'placeholders' => ['reference']],
        'withdrawal.rejected' => ['title' => 'Pencairan ditolak', 'body' => 'Pengajuan pencairan {reference} ditolak.', 'placeholders' => ['reference']],
        'withdrawal.ready' => ['title' => 'Pencairan siap diambil', 'body' => 'Pencairan {reference} siap diambil.', 'placeholders' => ['reference']],
        'withdrawal.paid' => ['title' => 'Pencairan selesai', 'body' => 'Pencairan {reference} telah selesai diproses.', 'placeholders' => ['reference']],
        'withdrawal.cancelled' => ['title' => 'Pencairan dibatalkan', 'body' => 'Pencairan {reference} dibatalkan.', 'placeholders' => ['reference']],
        'withdrawal.expired' => ['title' => 'Pencairan kedaluwarsa', 'body' => 'Pencairan {reference} kedaluwarsa.', 'placeholders' => ['reference']],
        'grocery.requested' => ['title' => 'Pengajuan sembako diterima', 'body' => 'Pengajuan sembako {reference} telah diterima.', 'placeholders' => ['reference']],
        'grocery.approved' => ['title' => 'Penukaran sembako disetujui', 'body' => 'Penukaran sembako {reference} disetujui.', 'placeholders' => ['reference']],
        'grocery.rejected' => ['title' => 'Penukaran sembako ditolak', 'body' => 'Penukaran sembako {reference} ditolak.', 'placeholders' => ['reference']],
        'grocery.prepared' => ['title' => 'Paket sembako disiapkan', 'body' => 'Paket sembako {reference} sedang disiapkan.', 'placeholders' => ['reference']],
        'grocery.ready' => ['title' => 'Paket sembako siap diambil', 'body' => 'Paket sembako {reference} siap diambil.', 'placeholders' => ['reference']],
        'grocery.completed' => ['title' => 'Penukaran sembako selesai', 'body' => 'Penukaran {reference} telah diserahkan.', 'placeholders' => ['reference']],
        'grocery.cancelled' => ['title' => 'Penukaran sembako dibatalkan', 'body' => 'Penukaran sembako {reference} dibatalkan.', 'placeholders' => ['reference']],
        'grocery.expired' => ['title' => 'Penukaran sembako kedaluwarsa', 'body' => 'Penukaran sembako {reference} kedaluwarsa.', 'placeholders' => ['reference']],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /** @return array{title: string, body: string, placeholders: list<string>} */
    public static function definition(string $type): array
    {
        $template = self::TEMPLATES[$type] ?? null;
        if ($template === null) {
            throw ValidationException::withMessages(['type' => 'Template notifikasi tidak diizinkan.']);
        }

        return $template;
    }

    /** @param array<string, scalar> $values
     * @return array{title: string, body: string}
     */
    public static function render(string $type, array $values): array
    {
        $template = self::definition($type);
        $unknown = array_diff(array_keys($values), $template['placeholders']);
        if ($unknown !== [] || array_diff($template['placeholders'], array_keys($values)) !== []) {
            throw ValidationException::withMessages(['template' => 'Placeholder notifikasi tidak diizinkan atau tidak lengkap.']);
        }

        $escaped = array_map(static fn (int|float|string|bool $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $values);

        return [
            'title' => strtr($template['title'], array_combine(array_map(static fn (string $key): string => '{'.$key.'}', array_keys($escaped)), array_values($escaped))),
            'body' => strtr($template['body'], array_combine(array_map(static fn (string $key): string => '{'.$key.'}', array_keys($escaped)), array_values($escaped))),
        ];
    }
}
