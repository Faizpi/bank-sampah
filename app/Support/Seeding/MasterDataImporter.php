<?php

declare(strict_types=1);

namespace App\Support\Seeding;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Shared by the main seeder and the explicitly authorised hosting-dummy installer. */
final class MasterDataImporter
{
    public function install(MasterData $master): void
    {
        DB::transaction(function () use ($master): void {
            foreach ([...array_keys(MasterData::COLUMNS), 'deposits', 'ledger_entries', 'balance_holds', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new LogicException('Seed utama hanya untuk database kosong. Data yang ada tidak akan ditimpa; backup dan gunakan reset yang eksplisit.');
                }
            }
            foreach (array_keys(MasterData::COLUMNS) as $table) {
                if ($master->tables[$table] !== []) {
                    DB::table($table)->insert($master->tables[$table]);
                }
            }
            foreach (CustomerProfile::query()->get() as $profile) {
                $token = QrToken::generate();
                $profile->forceFill([
                    'qr_token_hash' => $token->hash(),
                    'qr_token_encrypted' => $token->value(),
                    'qr_rotated_at' => now(),
                ])->save();
            }
        });
    }
}
