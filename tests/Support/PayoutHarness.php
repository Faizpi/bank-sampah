<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class PayoutHarness
{
    public static function configure(): void
    {
        config()->set(['payouts.enabled' => true, 'payouts.database' => DB::connection()->getDatabaseName(),
            'payouts.secret_key' => 'xnd_development_FAKE_KEY_FOR_TESTS_ONLY', 'payouts.webhook_token' => str_repeat('w', 40)]);
        foreach (['bca', 'mandiri', 'bri', 'bni', 'dana', 'gopay'] as $channel) {
            config()->set('payouts.routing.'.$channel, 'TEST_'.strtoupper($channel));
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    public static function credit(User $owner, User $officer): void
    {
        $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
        $deposits = app(DepositService::class);
        $deposits->finalize($officer, $deposits->createDraft($officer, $owner), (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50']]);
    }

    /** @return array<string, mixed> */
    public static function request(string $channel = 'dana'): array
    {
        return ['channel' => $channel, 'name' => 'Siti Aminah', 'number' => in_array($channel, ['dana', 'gopay'], true) ? '081234567890' : '0123456789', 'amount' => 20000, 'confirmed' => true];
    }

    /** @return array<string, mixed> */
    public static function response(SandboxPayout $row, string $status = 'SUCCEEDED'): array
    {
        return ['payout_id' => $row->provider_id ?? 'po-test-'.$row->id, 'reference_id' => $row->reference_id,
            'status' => $status, 'business_id' => 'test-business', 'source_currency' => 'IDR', 'destination_currency' => 'IDR',
            'source_amount' => $row->amount, 'destination_amount' => $row->amount, 'recipient' => $row->request_payload['recipient']];
    }
}
