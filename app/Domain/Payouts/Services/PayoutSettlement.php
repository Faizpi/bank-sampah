<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payouts\Models\SandboxPayout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class PayoutSettlement
{
    public function __construct(private LedgerService $ledger, private AuditLogger $audit) {}

    /** Apply only an authenticated API response, never an unverified callback body.
     * @param  array<string, mixed>  $data
     */
    public function apply(int $id, array $data): SandboxPayout
    {
        return DB::transaction(function () use ($id, $data): SandboxPayout {
            $row = SandboxPayout::query()->lockForUpdate()->findOrFail($id);
            $this->assertMatches($row, $data);
            $providerState = (string) $data['status'];
            $row->forceFill(['provider_id' => $data['payout_id'], 'business_id' => $data['business_id'], 'provider_status' => $providerState, 'lease' => null, 'leased_at' => null, 'next_check_at' => now()->addMinute()]);
            if ($row->status === 'reversed' || in_array($row->status, ['failed', 'cancelled'], true)) {
                if (! in_array($providerState, ['FAILED', 'REJECTED', 'COMPLIANCE_REJECTED', 'CANCELLED', 'REVERSED'], true)) {
                    $row->failure_code = 'STATUS_CONFLICT';
                }
                $row->next_check_at = null;
                $row->save();

                return $row;
            }
            if (in_array($providerState, ['SUCCEEDED', 'REVERSED'], true)) {
                if ($row->ledger_entry_id === null) {
                    $entry = $this->ledger->convertHold(app(PayoutIntegrity::class)->hold($row), 'payout:'.$row->id.':settlement');
                    $row->forceFill(['ledger_entry_id' => $entry->id, 'settled_at' => now(), 'status' => 'succeeded', 'failure_code' => null]);
                    $this->audit->record(null, 'payout.succeeded', $row, [], ['environment' => 'test', 'ledger_entry_id' => $entry->id], (string) Str::uuid());
                }
                if ($providerState === 'REVERSED' && $row->reversal_entry_id === null) {
                    $account = LedgerAccount::query()->where('user_id', $row->customer_id)->lockForUpdate()->firstOrFail();
                    $entry = LedgerEntry::query()->firstOrCreate(['source_key' => 'payout:'.$row->id.':reversal'], [
                        'entry_number' => 'LED-'.Str::upper(Str::random(16)), 'ledger_account_id' => $account->id,
                        'direction' => LedgerEntry::DIRECTION_IN, 'kind' => LedgerEntry::KIND_REVERSAL, 'amount' => $row->amount,
                        'source_type' => SandboxPayout::class, 'source_id' => $row->id, 'related_entry_id' => $row->ledger_entry_id,
                        'effective_at' => now(), 'balance_after' => $account->currentAvailableBalance() + $row->amount,
                    ]);
                    $row->forceFill(['reversal_entry_id' => $entry->id, 'reversed_at' => now(), 'status' => 'reversed']);
                    $this->audit->record(null, 'payout.reversed', $row, [], ['environment' => 'test', 'ledger_entry_id' => $entry->id], (string) Str::uuid());
                }
                $row->next_check_at = null;
            } elseif ($row->ledger_entry_id !== null) {
                // Never regress a completed debit or release a converted hold.
                $row->failure_code = 'STATUS_CONFLICT';
                $row->next_check_at = null;
            } elseif (in_array($providerState, ['FAILED', 'REJECTED', 'COMPLIANCE_REJECTED', 'CANCELLED'], true)) {
                $this->ledger->releaseHold(app(PayoutIntegrity::class)->hold($row));
                $failure = $data['failure_code'] ?? $providerState;
                $row->forceFill(['status' => 'failed', 'failure_code' => is_string($failure) && preg_match('/^[A-Z0-9_]{1,100}$/D', $failure) === 1 ? $failure : 'PROVIDER_REJECTED', 'next_check_at' => null]);
                $this->audit->record(null, 'payout.failed', $row, [], ['environment' => 'test', 'code' => $row->failure_code], (string) Str::uuid());
            } else {
                $row->status = 'pending';
            }
            $row->save();
            app(PayoutService::class)->notify($row);

            return $row;
        }, 5);
    }

    /** @param array<string, mixed> $data */
    private function assertMatches(SandboxPayout $row, array $data): void
    {
        $expected = $row->request_payload['recipient']['account_details'];
        $actual = data_get($data, 'recipient.account_details', []);
        $valid = $row->environment === 'test' && is_array($actual)
            && is_string($data['payout_id'] ?? null) && preg_match('/^po-[A-Za-z0-9-]{1,90}$/D', $data['payout_id']) === 1
            && ($row->provider_id === null || $row->provider_id === $data['payout_id'])
            && ($data['reference_id'] ?? null) === $row->reference_id
            && ($data['source_currency'] ?? null) === 'IDR' && ($data['destination_currency'] ?? null) === 'IDR'
            && is_int($data['source_amount'] ?? null) && $data['source_amount'] === $row->amount
            && is_int($data['destination_amount'] ?? null) && $data['destination_amount'] === $row->amount
            && is_string($data['business_id'] ?? null) && $data['business_id'] !== ''
            && ($row->business_id === null || $row->business_id === $data['business_id'])
            && is_string($data['status'] ?? null) && strlen($data['status']) <= 40;
        foreach (['account_number', 'account_country', 'currency', 'routing_type_1', 'routing_value_1'] as $field) {
            $valid = $valid && ($actual[$field] ?? null) === $expected[$field];
        }
        if (! $valid) {
            throw ValidationException::withMessages(['payout' => 'Identitas, tujuan, nominal, atau mata uang provider tidak sesuai pengajuan. Dana tetap menunggu pemeriksaan.']);
        }
    }
}
