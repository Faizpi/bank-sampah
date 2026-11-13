<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\XenditPayoutApi;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Support\Facades\Schema;
use Throwable;
use UnexpectedValueException;

final class PayoutDiagnose extends Command
{
    protected $signature = 'payouts:diagnose';

    protected $description = 'Periksa kesiapan sandbox tanpa menampilkan rahasia atau mengirim payout.';

    public function handle(): int
    {
        $configured = PayoutAccess::configured();
        $tables = Schema::hasTable('sandbox_payouts') && Schema::hasTable('sandbox_payout_events');
        $this->line(($configured ? '[OK]' : '[FAIL]').' konfigurasi Test Mode dan konfirmasi database simulasi');
        $this->line(($tables ? '[OK]' : '[FAIL]').' migrasi pencairan simulasi');
        foreach (app(PayoutChannels::class)->all() as $row) {
            $this->line(($row['ready'] ? '[OK]' : '[INFO]').' routing v3 '.$row['name']);
        }
        if ($tables) {
            $this->line('Permintaan berstatus tidak pasti: '.SandboxPayout::query()->where('status', 'uncertain')->count());
            foreach (SandboxPayout::query()->whereIn('status', ['uncertain', 'failed'])->whereNotNull('failure_code')->latest('id')->limit(5)->get() as $row) {
                $this->line($row->request_number.' | '.$row->label().' | '.$row->failure_code);
                if ($row->failureExplanation() !== null) {
                    $this->line($row->failureExplanation());
                }
                $this->inspectRequest($row);
                if ($configured && $row->status === 'uncertain' && $row->provider_id !== null) {
                    $this->inspectProvider($row);
                }
            }
        }
        $this->line('[INFO] Status tidak pasti yang memiliki ID diperiksa ke Xendit dengan GET saja. Tidak ada payout baru atau perubahan saldo.');
        $this->line('Tidak ada uang riil yang dikirim. Kesiapan channel perlu dibuktikan melalui akun Test Mode.');
        $this->line('Simulasi memakai saldo pada database yang dikonfirmasi. Gunakan hanya data dan saldo uji.');

        return $configured && $tables ? self::SUCCESS : self::FAILURE;
    }

    private function inspectProvider(SandboxPayout $row): void
    {
        try {
            $data = app(XenditPayoutApi::class)->find($row->provider_id);
            $expected = data_get($this->readSnapshot($row), 'recipient.account_details', []);
        } catch (Throwable) {
            $this->line('[INFO] Status provider tidak dapat dibaca. Tidak ada data atau rahasia respons yang ditampilkan.');

            return;
        }

        $status = $data['status'] ?? null;
        $states = ['ACCEPTED', 'PENDING_COMPLIANCE_ASSESSMENT', 'REJECTED', 'ROUTING', 'REQUESTED', 'READY', 'LOCKED', 'EXPIRED', 'FAILED', 'SUCCEEDED', 'CANCELLED', 'REVERSED'];
        $this->line('[INFO] Status Xendit: '.(is_string($status) && in_array($status, $states, true) ? $status : 'tidak dikenali').'.');
        $this->line('[INFO] Identitas provider sebelumnya tervalidasi: '.($row->business_id !== null ? 'ya' : 'belum').'.');

        $checks = [
            'payout_id' => ($data['payout_id'] ?? null) === $row->provider_id,
            'reference_id' => ($data['reference_id'] ?? null) === $row->reference_id,
            'business_id' => is_string($data['business_id'] ?? null) && $data['business_id'] !== '' && ($row->business_id === null || $data['business_id'] === $row->business_id),
            'source_currency' => ($data['source_currency'] ?? null) === 'IDR',
            'source_amount' => is_int($data['source_amount'] ?? null) && $data['source_amount'] === $row->amount,
            'destination_currency' => ($data['destination_currency'] ?? null) === 'IDR',
            'destination_amount' => is_int($data['destination_amount'] ?? null) && $data['destination_amount'] === $row->amount,
        ];
        foreach (['account_number', 'account_country', 'currency', 'routing_type_1', 'routing_value_1'] as $field) {
            $checks['recipient.account_details.'.$field] = is_array($expected)
                && array_key_exists($field, $expected)
                && data_get($data, 'recipient.account_details.'.$field) === $expected[$field];
        }
        foreach ($checks as $field => $matches) {
            $value = data_get($data, $field);
            $result = $value === null ? 'tidak ada' : ($matches ? 'sesuai' : 'tidak cocok');
            $this->line('[INFO] '.$field.': '.$result.'.');
        }
    }

    private function inspectRequest(SandboxPayout $row): void
    {
        try {
            $payload = $this->readSnapshot($row);
        } catch (Throwable) {
            $this->line('[FAIL] Snapshot request tidak dapat dibaca. Periksa APP_KEY hosting tanpa menggantinya.');

            return;
        }
        $validator = validator($payload, [
            'reference_id' => ['required', 'uuid'],
            'recipient.type' => ['required', 'in:INDIVIDUAL'],
            'recipient.given_name' => ['required', 'string', 'max:50'],
            'recipient.surname' => ['required', 'string', 'max:50'],
            'recipient.relationship' => ['required', 'in:CUSTOMER'],
            'recipient.address.country' => ['required', 'in:ID'],
            'recipient.address.street_line_1' => ['required', 'string', 'max:255'],
            'recipient.address.city' => ['required', 'string', 'max:255'],
            'recipient.address.province_state' => ['required', 'string', 'max:255'],
            'recipient.address.postal_code' => ['required', 'regex:/^[0-9]{5}$/D'],
            'recipient.account_details.currency' => ['required', 'in:IDR'],
            'recipient.account_details.account_country' => ['required', 'in:ID'],
            'recipient.account_details.account_holder_name' => ['required', 'string', 'max:255'],
            'recipient.account_details.account_number' => ['required', 'string'],
            'recipient.account_details.routing_type_1' => ['required', 'in:SWIFT,WALLET'],
            'recipient.account_details.routing_value_1' => ['required', 'string'],
            'payout_details.source_currency' => ['required', 'in:IDR'],
            'payout_details.destination_currency' => ['required', 'in:IDR'],
            'payout_details.source_amount' => ['required', 'integer', 'min:1'],
            'payout_details.destination_amount' => ['prohibited'],
            'source_of_fund' => ['required', 'string'],
            'purpose_code' => ['required', 'string'],
        ]);
        $fields = $validator->errors()->keys();
        if (($payload['reference_id'] ?? null) !== $row->reference_id) {
            $fields[] = 'reference_id';
        }
        if (! is_int(data_get($payload, 'payout_details.source_amount')) || data_get($payload, 'payout_details.source_amount') !== $row->amount) {
            $fields[] = 'payout_details.source_amount';
        }
        if ($fields !== []) {
            $this->line('[FAIL] Isian request tersimpan perlu diperiksa: '.implode(', ', array_unique($fields)).'.');
        } else {
            $this->line('[OK] Struktur inti request tersimpan (bukan validasi khusus akun Xendit).');
        }
        $expectedRoutingType = in_array($row->channel, ['dana', 'gopay'], true) ? 'WALLET' : 'SWIFT';
        if (data_get($payload, 'recipient.account_details.routing_type_1') !== $expectedRoutingType) {
            $this->line('[FAIL] Jenis routing tidak sesuai metode tujuan.');
        }
        $this->line('[INFO] Nilai isian dan rahasia tidak ditampilkan; tidak ada pengiriman ulang.');
        if ($row->failure_code === 'payout.http_400:API_VALIDATION_ERROR') {
            $this->line('[INFO] Nama field penolakan lama belum tersimpan. Penyebab khusus akun belum dapat dipastikan.');
        }
    }

    /** @return array<array-key, mixed> */
    private function readSnapshot(SandboxPayout $row): array
    {
        $encrypted = $row->getRawOriginal('request_payload');
        if (! is_string($encrypted)) {
            throw new UnexpectedValueException('Request snapshot is unavailable.');
        }
        $payload = json_decode(app(Encrypter::class)->decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) {
            throw new UnexpectedValueException('Request snapshot is not an object.');
        }

        return $payload;
    }
}
