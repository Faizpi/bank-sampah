<?php

declare(strict_types=1);

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== getenv('DB_DATABASE') || ! preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', DB::connection()->getDatabaseName())) {
    throw new RuntimeException('Only the named disposable browser database may be verified.');
}
$checks = [
    'four_roles' => User::count() === 4,
    'pickup_completed' => PickupRequest::where('status', 'selesai')->count() === 1,
    'cash_paid' => WithdrawalRequest::where('status', 'sudah_dibayar')->count() === 1,
    'cash_cancelled' => WithdrawalRequest::where('status', 'dibatalkan')->count() === 1,
    'grocery_completed' => GroceryRedemption::where('status', 'selesai')->count() === 1,
    'ledger_exactly_once' => LedgerEntry::count() === 6 && LedgerEntry::where('direction', 'keluar')->count() === 2,
    'no_required_media' => Media::count() === 0,
    'no_active_hold' => BalanceHold::where('status', 'aktif')->count() === 0,
    'correct_balance' => User::where('username', 'warga')->firstOrFail()->ledgerAccount->availableBalance() === 86500,
];
if (in_array(false, $checks, true)) {
    throw new RuntimeException('Browser ledger verification failed: '.json_encode($checks, JSON_THROW_ON_ERROR));
}
echo json_encode($checks, JSON_THROW_ON_ERROR).PHP_EOL;
