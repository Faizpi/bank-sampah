<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Services\TelegramFiles;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

it('generates the same owner scoped Excel report as the web and refuses another role', function (): void {
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $citizen = User::where('username', 'warga')->firstOrFail();
    $officer = User::where('username', 'petugas')->firstOrFail();
    $treasurer = User::where('username', 'bendahara')->firstOrFail();
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $deposits = app(DepositService::class);
    $deposits->finalize($officer, $deposits->createDraft($officer, $citizen), (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50']]);
    $withdrawals = app(WithdrawalService::class);
    $first = $withdrawals->request($citizen, ['amount' => 20000, 'pickup_date' => today()->toDateString(), 'pickup_location' => 'Loket'], (string) str()->uuid());
    $withdrawals->pay($treasurer, $withdrawals->claim($treasurer, $first), null, (string) str()->uuid());
    $other = User::factory()->create(['role' => 'bendahara', 'status' => UserStatus::Active]);
    $areaId = $treasurer->staffProfile->service_area_id;
    $staff = StaffProfile::query()->create(['user_id' => $other->id, 'staff_number' => 'TREASURER-OTHER', 'service_area_id' => $areaId, 'active_from' => today()->subYear(), 'active_to' => null]);
    $staff->serviceAreas()->updateOrCreate(['service_area_id' => $areaId], ['active_from' => today()->subYear()]);
    $second = $withdrawals->request($citizen, ['amount' => 30000, 'pickup_date' => today()->toDateString(), 'pickup_location' => 'Loket'], (string) str()->uuid());
    $withdrawals->pay($other, $withdrawals->claim($other, $second), null, (string) str()->uuid());
    Bot::link($treasurer, '10003');
    Bot::text('10003', '/menu');
    Bot::click('10003', 'Laporan pembayaran');
    Bot::click('10003', 'Tujuh hari terakhir');
    expect(Bot::lastText('10003'))->toContain('1 transaksi', '20.000')->not->toContain('30.000');
    Bot::click('10003', 'Kirim Excel');
    $message = TelegramOutbox::where('kind', 'report')->firstOrFail();
    $file = app(TelegramFiles::class)->outgoing($message, $treasurer);
    expect(substr($file['bytes'], 0, 2))->toBe('PK')->and($file['filename'])->toEndWith('.xlsx');
    $export = ReportExport::where('requester_id', $treasurer->id)->firstOrFail();
    $media = app(ReportExportService::class)->download($treasurer, $export);
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk($media->disk)->path($media->path)))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($sheet)->toContain($first->request_number)->not->toContain($second->request_number);
    expect(fn () => app(TelegramFiles::class)->outgoing($message, $officer))->toThrow(HttpException::class);
});
