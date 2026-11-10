<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Notifications\Data\NotificationPayload;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PayoutService
{
    public function __construct(private PayoutAccess $access, private PayoutChannels $channels, private LedgerService $ledger, private AuditLogger $audit) {}

    /** @param array<string, mixed> $data
     * @return array{channel: string, name: string, number: string, amount: int, service_area_id: int, rt_id: int}
     */
    public function quote(User $actor, array $data): array
    {
        $this->access->requireConfigured();
        $actor = $this->actor($actor);
        $validated = validator($data, [
            'channel' => ['required', 'string', 'in:bca,mandiri,bri,bni,dana,gopay'],
            'name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\\p{L}\\p{N} .'-]+$/u"],
            'number' => ['required', 'string', 'max:30'],
            'amount' => ['required', 'integer', 'min:'.(int) config('app.withdrawal_minimum_amount', 10000), 'max:'.(int) config('payouts.max_amount')],
            'service_area_id' => ['nullable', 'integer'],
        ], [
            'channel.required' => 'Pilih bank atau dompet digital tujuan.',
            'name.required' => 'Isi nama pemilik tujuan uji.', 'name.regex' => 'Nama pemilik tidak valid.',
            'number.required' => 'Isi nomor rekening atau dompet digital tujuan uji.',
            'amount.min' => 'Nominal belum memenuhi minimum pencairan.', 'amount.max' => 'Nominal melebihi batas pencairan simulasi.',
        ])->validate();
        $area = app(WithdrawalService::class)->resolveRequestArea($actor, $validated['service_area_id'] ?? null);
        $number = $this->channels->normalizeNumber($validated['channel'], $validated['number']);
        $name = trim(preg_replace('/\s+/u', ' ', $validated['name']) ?? '');
        $parts = explode(' ', $name);
        $surname = array_pop($parts);
        if ($parts === [] || mb_strlen(implode(' ', $parts)) > 50 || mb_strlen($surname) > 50) {
            throw ValidationException::withMessages(['name' => 'Gunakan nama tujuan uji dua kata atau lebih (contoh: Siti Aminah). Bagian nama depan dan belakang maksimal 50 karakter.']);
        }

        return ['channel' => $validated['channel'], 'name' => $name, 'number' => $number, 'amount' => (int) $validated['amount'], 'service_area_id' => $area->id, 'rt_id' => (int) $actor->customerProfile?->rt_id];
    }

    /** @param array<string, mixed> $data */
    public function request(User $actor, array $data, string $key): SandboxPayout
    {
        if (($data['confirmed'] ?? null) !== true) {
            throw ValidationException::withMessages(['payout' => 'Konfirmasi penggunaan data tujuan uji dan pencairan simulasi terlebih dahulu.']);
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{15,190}$/D', $key) !== 1) {
            throw ValidationException::withMessages(['payout' => 'Kunci pengajuan tidak valid.']);
        }
        $quote = $this->quote($actor, $data);
        $hash = hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $quote, $hash, $key): SandboxPayout {
            $actor = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $this->actor($actor);
            $existing = IdempotencyKey::activeForUpdate($actor->id, 'payout.request', $key);
            if ($existing !== null) {
                if ($existing->payload_hash !== $hash || $existing->result_id === null) {
                    throw ValidationException::withMessages(['payout' => 'Kunci pengajuan sudah dipakai untuk data berbeda.']);
                }

                return SandboxPayout::query()->where('customer_id', $actor->id)->findOrFail($existing->result_id);
            }
            $identity = IdempotencyKey::query()->create(['actor_id' => $actor->id, 'scope' => 'payout.request', 'key' => $key, 'payload_hash' => $hash, 'status' => 'processing']);
            $reference = (string) Str::uuid();
            $channel = $this->channels->get($quote['channel']);
            $nameParts = explode(' ', $quote['name']);
            $surname = count($nameParts) > 1 ? array_pop($nameParts) : null;
            $recipient = ['type' => 'INDIVIDUAL', 'given_name' => implode(' ', $nameParts), 'relationship' => 'CUSTOMER', 'account_details' => [
                'currency' => 'IDR', 'account_country' => 'ID', 'account_holder_name' => $quote['name'], 'account_number' => $quote['number'],
                'routing_type_1' => $channel['wallet'] ? 'WALLET' : 'SWIFT', 'routing_value_1' => $channel['routing'],
            ]];
            if ($surname !== null) {
                $recipient['surname'] = $surname;
            }
            $payout = SandboxPayout::query()->create([
                'request_number' => 'SIM-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8)),
                'customer_id' => $actor->id, 'rt_id' => $quote['rt_id'], 'service_area_id' => $quote['service_area_id'],
                'amount' => $quote['amount'], 'channel' => $quote['channel'], 'recipient' => ['name' => $quote['name'], 'number' => $quote['number']],
                'payload_hash' => $hash, 'reference_id' => $reference, 'provider_key' => (string) Str::uuid(), 'environment' => 'test',
                'status' => 'queued', 'next_check_at' => now(), 'consent_version' => 'payout-sandbox-v1', 'consented_at' => now(),
                'request_payload' => ['reference_id' => $reference, 'recipient' => $recipient,
                    'payout_details' => ['source_currency' => 'IDR', 'source_amount' => $quote['amount'], 'destination_currency' => 'IDR'],
                    'source_of_fund' => 'OTHER', 'purpose_code' => 'OTHER', 'description' => 'Simulasi Bank Sampah Sindangheula'],
            ]);
            $hold = $this->ledger->createHold($actor, $payout, $payout->amount, 'payout:'.$payout->id.':hold');
            $payout->forceFill(['balance_hold_id' => $hold->id])->save();
            $identity->forceFill(['status' => 'succeeded', 'result_type' => SandboxPayout::class, 'result_id' => $payout->id])->save();
            $this->audit->record($actor, 'payout.requested', $payout, [], ['channel' => $payout->channel, 'amount' => $payout->amount, 'environment' => 'test'], (string) Str::uuid());
            $this->notify($payout);

            return $payout;
        }, 5);
    }

    public function cancel(User $actor, SandboxPayout $payout): SandboxPayout
    {
        return DB::transaction(function () use ($actor, $payout): SandboxPayout {
            $this->actor(User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail());
            $row = SandboxPayout::query()->where('customer_id', $actor->id)->lockForUpdate()->findOrFail($payout->id);
            if ($row->status === 'cancelled') {
                return $row;
            }
            if ($row->status !== 'queued' || $row->lease !== null || $row->provider_id !== null) {
                throw ValidationException::withMessages(['payout' => 'Permintaan sudah diproses. Tunggu hasil pemeriksaan status, jangan mengirim pencairan baru untuk transaksi yang sama.']);
            }
            $this->ledger->releaseHold(app(PayoutIntegrity::class)->hold($row));
            $row->forceFill(['status' => 'cancelled', 'next_check_at' => null])->save();
            $this->audit->record($actor, 'payout.cancelled', $row, [], ['environment' => 'test'], (string) Str::uuid());
            $this->notify($row);

            return $row;
        }, 5);
    }

    public function notify(SandboxPayout $payout): void
    {
        NotificationRequested::dispatch(new NotificationPayload(
            recipientId: $payout->customer_id, type: 'payout.updated', title: 'Pencairan digital (simulasi)',
            body: $payout->request_number.': '.$payout->label().'. Tidak ada dana riil yang dikirim.',
            reference: '/warga/pencairan-digital/'.$payout->id, dedupeKey: 'payout:'.$payout->id.':'.$payout->status,
        ));
    }

    private function actor(User $actor): User
    {
        if (! $actor->hasRole('warga') || $actor->status->value !== 'aktif' || ! app(PermissionChecker::class)->allows($actor, 'withdrawal.request')) {
            throw new AuthorizationException('Pencairan simulasi hanya diajukan oleh pemilik akun Warga aktif.');
        }

        return $actor;
    }
}
