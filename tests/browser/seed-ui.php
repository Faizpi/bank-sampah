<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Support\RegionOptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== getenv('DB_DATABASE') || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('UI fixtures require the requested disposable loopback MySQL.');
}
$admin = User::where('username', 'admin')->firstOrFail();
$officer = User::where('username', 'petugas')->firstOrFail();
$rt = Rt::firstOrFail();
$type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
$services = app(MobileServiceService::class);
$draft = $services->create($admin, $rt->rw_id, $rt->id, 'Titik draft audit UI', today()->addDay()->addHours(9)->toDateTimeString(), today()->addDay()->addHours(11)->toDateTimeString(), 20, 'Catatan jadwal tersimpan', [$officer->id], [$type->id]);
$active = $services->create($admin, $rt->rw_id, $rt->id, 'Titik aktif audit UI', now()->subHour()->toDateTimeString(), now()->addHour()->toDateTimeString(), 20, '', [$officer->id], [$type->id]);
$services->transition($admin, $active, MobileServiceStatus::Published);
$services->transition($officer, $active->fresh(), MobileServiceStatus::Open);
$target = app(TargetService::class)->create($admin, 'Target cakupan audit UI', 'Pertahankan wilayah saat edit', today()->toDateString(), today()->addMonth()->toDateString(), '100.000', true, [['rt_id' => $rt->id]]);
echo json_encode(['mobileDraftId' => $draft->id, 'activeMobileId' => $active->id, 'targetId' => $target->id, 'rtLabel' => RegionOptions::label($rt), 'expectedCashTotal' => (int) WithdrawalRequest::query()->whereDate('paid_at', today())->sum('amount')], JSON_THROW_ON_ERROR).PHP_EOL;
