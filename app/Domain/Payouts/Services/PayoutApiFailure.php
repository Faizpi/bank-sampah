<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use RuntimeException;

final class PayoutApiFailure extends RuntimeException
{
    private const REJECTION_CODES = [
        400 => ['API_VALIDATION_ERROR', 'INVALID_VALUE_ERROR', 'INVALID_QUOTE'],
        401 => ['INVALID_API_KEY'],
        403 => ['REQUEST_FORBIDDEN_ERROR', 'INVALID_MERCHANT_SETTINGS'],
        404 => ['DATA_NOT_FOUND'],
    ];

    private const FIELD_NAMES = [
        'api-version' => 'versi API',
        'idempotency-key' => 'kunci pengiriman',
        'reference_id' => 'referensi pengajuan',
        'sender' => 'data pengirim',
        'sender.type' => 'jenis pengirim',
        'sender.business_name' => 'nama usaha pengirim',
        'sender.given_name' => 'nama depan pengirim',
        'sender.surname' => 'nama belakang pengirim',
        'sender.address' => 'alamat pengirim',
        'recipient' => 'data penerima',
        'recipient.type' => 'jenis penerima',
        'recipient.given_name' => 'nama depan penerima',
        'recipient.surname' => 'nama belakang penerima',
        'recipient.relationship' => 'hubungan penerima',
        'recipient.details' => 'rincian penerima',
        'recipient.details.personal_mobile_number' => 'nomor telepon penerima',
        'recipient.address' => 'alamat penerima',
        'recipient.address.country' => 'negara penerima',
        'recipient.address.city' => 'kota penerima',
        'recipient.address.province_state' => 'provinsi penerima',
        'recipient.address.street_line_1' => 'alamat penerima',
        'recipient.address.postal_code' => 'kode pos penerima',
        'recipient.account_details' => 'tujuan pencairan',
        'recipient.account_details.currency' => 'mata uang tujuan',
        'recipient.account_details.account_country' => 'negara rekening tujuan',
        'recipient.account_details.account_holder_name' => 'nama pemilik tujuan',
        'recipient.account_details.account_number' => 'nomor tujuan',
        'recipient.account_details.routing_type_1' => 'jenis routing tujuan',
        'recipient.account_details.routing_value_1' => 'routing tujuan',
        'payout_details' => 'rincian pencairan',
        'payout_details.source_currency' => 'mata uang sumber',
        'payout_details.destination_currency' => 'mata uang tujuan',
        'payout_details.source_amount' => 'nominal pencairan',
        'payout_details.destination_amount' => 'nominal tujuan',
        'source_of_fund' => 'sumber dana',
        'purpose_code' => 'tujuan penggunaan dana',
    ];

    public function __construct(public readonly string $codeName, public readonly bool $uncertain = true)
    {
        parent::__construct($codeName);
    }

    public static function http(int $status, mixed $data, bool $creating): self
    {
        $rawCode = is_array($data) ? ($data['error_code'] ?? null) : null;
        $knownCodes = [...array_merge(...array_values(self::REJECTION_CODES)), 'DUPLICATE_ERROR', 'SERVER_ERROR'];
        $providerCode = is_string($rawCode) && in_array($rawCode, $knownCodes, true) ? $rawCode : null;
        $code = 'payout.http_'.$status.($providerCode === null ? '' : ':'.$providerCode);
        // Never retain provider messages, values, body, headers, or unknown field paths.
        $budget = 50;
        $field = $providerCode === null ? null : self::validationField($data['errors'] ?? [], '', 0, $budget);
        if ($field !== null && strlen($code.':'.$field) <= 100) {
            $code .= ':'.$field;
        }
        $rejected = $creating && $providerCode !== null && in_array($providerCode, self::REJECTION_CODES[$status] ?? [], true);

        return new self($code, uncertain: ! $rejected);
    }

    private static function normalizeField(mixed $path): ?string
    {
        if (is_array($path) && count($path) <= 10 && count(array_filter($path, 'is_string')) === count($path)) {
            $path = implode('.', $path);
        }
        if (! is_string($path) || strlen($path) > 200) {
            return null;
        }
        $path = str_replace(['/', '[', ']', '"', "'"], ['.', '.', '', '', ''], $path);
        $path = ltrim($path, '$.');
        foreach (['request.body.', 'request.headers.', 'body.', 'headers.', 'data.'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return isset(self::FIELD_NAMES[$path]) ? $path : null;
    }

    private static function validationField(mixed $errors, string $prefix, int $depth, int &$budget): ?string
    {
        if ($depth > 6 || --$budget < 0) {
            return null;
        }
        if (is_string($errors)) {
            // Accept only an exact required-field template, never arbitrary messages or values.
            if (strlen($errors) <= 240 && preg_match('/^["\x27]([A-Za-z_.\/-]+)["\x27] is required\.?$/D', $errors, $matches) === 1) {
                return self::normalizeField($matches[1]);
            }

            return null;
        }
        if (! is_array($errors)) {
            return null;
        }
        foreach (['path', 'field', 'field_name', 'property', 'instancePath', 'dataPath', 'loc', 'parameter'] as $key) {
            $path = self::normalizeField($errors[$key] ?? null);
            if ($path !== null) {
                $missing = data_get($errors, 'params.missingProperty');
                if (is_string($missing)) {
                    $child = self::normalizeField($path.'.'.$missing);
                    if ($child !== null) {
                        return $child;
                    }
                }

                return $path;
            }
        }
        $missing = data_get($errors, 'params.missingProperty');
        if (is_string($missing)) {
            $field = self::normalizeField($prefix === '' ? $missing : $prefix.'.'.$missing);
            if ($field !== null) {
                return $field;
            }
        }
        foreach (array_slice($errors, 0, 25, true) as $key => $error) {
            // Free-text messages, input values, and context are deliberately not traversed.
            if (in_array($key, ['message', 'msg', 'value', 'input', 'context', 'params'], true)) {
                continue;
            }
            $candidate = is_string($key) ? ($prefix === '' ? $key : $prefix.'.'.$key) : $prefix;
            $known = self::normalizeField($candidate);
            $nested = is_int($key) || in_array($key, ['errors', 'details', 'children'], true) || $known !== null;
            if ($nested) {
                $field = self::validationField($error, $known ?? $prefix, $depth + 1, $budget);
                if ($field !== null) {
                    return $field;
                }
            }
            if ($known !== null) {
                return $known;
            }
        }

        return null;
    }

    public static function explanation(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        [$http, , $field] = array_pad(explode(':', $code, 3), 3, null);
        $message = match ($http) {
            'payout.http_400' => 'Xendit menolak format atau nilai pengajuan. Periksa data tujuan, nominal, dan konfigurasi routing.',
            'payout.http_401' => 'Development API Key Xendit tidak diterima. Periksa key pada .env hosting, lalu muat ulang konfigurasi.',
            'payout.http_403' => 'Xendit menolak akses. Periksa izin MONEY-OUT serta akses Payouts v3 pada akun Test Mode.',
            'payout.http_404' => 'Data atau layanan yang diminta tidak ditemukan pada Xendit. Periksa konfigurasi API.',
            'payout.http_409' => 'Kunci pengiriman bertentangan dengan permintaan sebelumnya. Jangan mengganti kunci atau mengajukan ulang sebelum rekonsiliasi.',
            'payout.transport' => 'Koneksi ke Xendit tidak dapat dipastikan. Saldo tetap disisihkan sampai hasil pengiriman diketahui.',
            'payout.response_unconfirmed' => 'Status dari Xendit belum dapat dipastikan. Saldo tetap disisihkan sampai pemeriksaan berikutnya.',
            default => null,
        };
        if ($message !== null && $field !== null && isset(self::FIELD_NAMES[$field])) {
            $message .= ' Isian yang dilaporkan: '.self::FIELD_NAMES[$field].'.';
        }

        return $message;
    }

    public static function publicMessage(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $reason = explode(':', $code, 2)[0];

        return match ($reason) {
            'INVALID_DESTINATION', 'ACCOUNT_NAME_MISMATCH' => 'Periksa kembali nama dan nomor rekening atau dompet digital tujuan.',
            'INSUFFICIENT_BALANCE' => 'Saldo uji layanan pencairan belum mencukupi.',
            'payout.transport', 'payout.response_unconfirmed', 'STALE_SUBMISSION' => 'Hasil pencairan masih diperiksa.',
            'ACCOUNT_UNAVAILABLE' => 'Akun belum dapat mengajukan pencairan.',
            default => 'Layanan pencairan belum dapat memproses pengajuan ini.',
        };
    }
}
