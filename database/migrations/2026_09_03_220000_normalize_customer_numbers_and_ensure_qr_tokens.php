<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = CustomerProfile::query()->with('user')->get();

        foreach ($profiles as $profile) {
            $updated = false;

            // 1. Normalize legacy customer_number (e.g. 'SH-00001' -> 'CST-00000001')
            $rawNumber = (string) $profile->customer_number;
            if (preg_match('/^SH-([0-9]+)$/', $rawNumber, $matches)) {
                $targetNumber = 'CST-'.str_pad($matches[1], 8, '0', STR_PAD_LEFT);
                if (! CustomerProfile::query()->where('customer_number', $targetNumber)->where('user_id', '!=', $profile->user_id)->exists()) {
                    $profile->customer_number = $targetNumber;
                    $updated = true;
                }
            } elseif ($rawNumber === '' && $profile->user?->status?->value === 'aktif') {
                do {
                    $newNumber = 'CST-'.str_pad((string) random_int(1, 99_999_999), 8, '0', STR_PAD_LEFT);
                } while (CustomerProfile::query()->where('customer_number', $newNumber)->exists());

                $profile->customer_number = $newNumber;
                $updated = true;
            }

            // 2. Ensure QR token is present and active for customer profile
            if ($profile->qr_token_hash === null || $profile->qr_token_encrypted === null) {
                $token = QrToken::generate();
                $profile->qr_token_hash = $token->hash();
                $profile->qr_token_encrypted = $token->value();
                $profile->qr_rotated_at = now();
                $updated = true;
            }

            if ($updated) {
                $profile->save();
            }
        }
    }

    public function down(): void
    {
        // Safe no-op down: preserving valid normalized customer numbers and tokens
    }
};
