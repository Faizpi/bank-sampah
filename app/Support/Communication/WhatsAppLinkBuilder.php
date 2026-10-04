<?php

declare(strict_types=1);

namespace App\Support\Communication;

use App\Support\Auth\PhoneNumber;
use Illuminate\Validation\ValidationException;

final class WhatsAppLinkBuilder
{
    /** @var array<string, array{placeholders: list<string>, text: string}> */
    private const TEMPLATES = [
        'support' => ['placeholders' => ['topic'], 'text' => 'Halo Bank Sampah Sindangheula, saya ingin menanyakan {topic}.'],
        'mobile-service' => ['placeholders' => ['service'], 'text' => 'Halo Bank Sampah Sindangheula, saya ingin menanyakan layanan keliling {service}.'],
        'announcement' => ['placeholders' => ['title'], 'text' => 'Halo Bank Sampah Sindangheula, saya ingin menanyakan pengumuman {title}.'],
    ];

    /** @param array<string, scalar> $values */
    public function build(string $phone, string $template, array $values): string
    {
        $normalized = PhoneNumber::normalize($phone);
        if (preg_match('/^62[0-9]{8,13}$/', $normalized) !== 1) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak valid.']);
        }
        $definition = self::TEMPLATES[$template] ?? null;
        if ($definition === null || array_diff(array_keys($values), $definition['placeholders']) !== [] || array_diff($definition['placeholders'], array_keys($values)) !== []) {
            throw ValidationException::withMessages(['template' => 'Template WhatsApp tidak diizinkan atau placeholder tidak lengkap.']);
        }
        $safe = array_map(static fn (int|float|string|bool $value): string => rawurlencode(trim((string) $value)), $values);
        $message = strtr($definition['text'], array_combine(array_map(static fn (string $key): string => '{'.$key.'}', array_keys($safe)), array_values($safe)));

        return 'https://wa.me/'.$normalized.'?text='.$message;
    }

    /** @return list<string> */
    public function templates(): array
    {
        return array_keys(self::TEMPLATES);
    }
}
