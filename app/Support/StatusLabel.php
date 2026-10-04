<?php

declare(strict_types=1);

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Str;

final class StatusLabel
{
    /** @var array<string, string> */
    private const LABELS = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'menunggu_pemeriksaan' => 'Menunggu pemeriksaan',
        'menunggu_persetujuan' => 'Menunggu persetujuan',
        'final' => 'Selesai',
        'dikoreksi' => 'Dikoreksi',
        'dibalik' => 'Dibatalkan',
        'disetujui' => 'Disetujui',
        'diterima' => 'Diterima',
        'dijadwalkan' => 'Dijadwalkan',
        'menuju_lokasi' => 'Menuju lokasi',
        'dijemput' => 'Sudah dijemput',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
        'siap_diambil' => 'Siap diambil',
        'sudah_dibayar' => 'Sudah dibayar',
        'kedaluwarsa' => 'Kedaluwarsa',
        'draf' => 'Draf',
        'dipublikasikan' => 'Dipublikasikan',
        'dibuka' => 'Sedang dibuka',
        'ditutup' => 'Ditutup',
        'menunggu' => 'Menunggu',
        'aktif' => 'Aktif',
        'nonaktif' => 'Nonaktif',
        'dikonversi' => 'Dikonversi',
        'dilepas' => 'Dilepas',
    ];

    public static function for(mixed $status): string
    {
        $value = $status instanceof BackedEnum ? (string) $status->value : (string) $status;

        return self::LABELS[$value] ?? Str::headline($value);
    }
}
