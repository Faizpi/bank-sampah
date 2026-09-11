<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('has all financial append-only triggers after the full migration set', function (): void {
    $expected = [
        'balance_holds_prevent_core_update',
        'balance_holds_prevent_delete',
        'ledger_entries_prevent_delete',
        'ledger_entries_prevent_update',
        'transaction_corrections_prevent_delete',
        'transaction_corrections_prevent_update',
        'transaction_reversals_prevent_delete',
        'transaction_reversals_prevent_update',
    ];
    sort($expected);

    expect(DB::getDriverName())->toBe('sqlite')
        ->and(DB::table('sqlite_master')
            ->where('type', 'trigger')
            ->whereIn('name', $expected)
            ->orderBy('name')
            ->pluck('name')
            ->all())->toBe($expected);
});

it('rejects direct financial updates and deletes without changing stored rows', function (): void {
    $users = User::factory()->count(3)->create();
    $timestamp = now();
    $accountId = DB::table('ledger_accounts')->insertGetId([
        'user_id' => $users[0]->id,
        'status' => 'aktif',
        'currency' => 'IDR',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $depositId = DB::table('deposits')->insertGetId([
        'deposit_number' => 'TRG-TEST-DEP',
        'customer_id' => $users[0]->id,
        'staff_id' => $users[1]->id,
        'method' => 'counter',
        'location' => 'test',
        'occurred_at' => $timestamp,
        'status' => 'draf',
        'total_weight_kg' => '1.000',
        'total_value' => 10000,
        'finalized_at' => null,
        'idempotency_key' => null,
        'verification_token_hash' => null,
        'verification_token_encrypted' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $entryId = DB::table('ledger_entries')->insertGetId([
        'entry_number' => 'TRG-TEST-ENTRY',
        'ledger_account_id' => $accountId,
        'direction' => 'credit',
        'kind' => 'deposit',
        'amount' => 10000,
        'source_type' => 'test',
        'source_id' => $depositId,
        'source_key' => 'test:trigger:entry',
        'related_entry_id' => null,
        'effective_at' => $timestamp,
        'balance_after' => 10000,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $holdId = DB::table('balance_holds')->insertGetId([
        'hold_number' => 'TRG-TEST-HOLD',
        'ledger_account_id' => $accountId,
        'source_type' => 'test',
        'source_id' => $depositId,
        'source_key' => 'test:trigger:hold',
        'amount' => 1000,
        'status' => 'aktif',
        'held_at' => $timestamp,
        'released_at' => null,
        'converted_at' => null,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $correctionId = DB::table('transaction_corrections')->insertGetId([
        'correction_number' => 'TRG-TEST-CORRECTION',
        'deposit_id' => $depositId,
        'reason' => 'Initial correction',
        'before_values' => json_encode(['total_value' => 10000], JSON_THROW_ON_ERROR),
        'after_values' => json_encode(['total_value' => 10100], JSON_THROW_ON_ERROR),
        'delta_value' => 100,
        'status' => 'final',
        'created_by' => $users[2]->id,
        'finalized_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $reversalId = DB::table('transaction_reversals')->insertGetId([
        'reversal_number' => 'TRG-TEST-REVERSAL',
        'original_deposit_id' => $depositId,
        'original_entry_id' => $entryId,
        'reason' => 'Initial reversal',
        'created_by' => $users[2]->id,
        'finalized_at' => $timestamp,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $updates = [
        ['table' => 'ledger_entries', 'id' => $entryId, 'column' => 'amount', 'original' => 10000, 'replacement' => 10001],
        ['table' => 'balance_holds', 'id' => $holdId, 'column' => 'amount', 'original' => 1000, 'replacement' => 1001],
        ['table' => 'transaction_corrections', 'id' => $correctionId, 'column' => 'reason', 'original' => 'Initial correction', 'replacement' => 'Tampered correction'],
        ['table' => 'transaction_reversals', 'id' => $reversalId, 'column' => 'reason', 'original' => 'Initial reversal', 'replacement' => 'Tampered reversal'],
    ];

    foreach ($updates as $update) {
        expect(fn (): int => DB::table($update['table'])
            ->where('id', $update['id'])
            ->update([$update['column'] => $update['replacement']]))
            ->toThrow(QueryException::class);
        expect(DB::table($update['table'])->where('id', $update['id'])->value($update['column']))
            ->toBe($update['original']);
    }

    $deletions = [
        ['table' => 'ledger_entries', 'id' => $entryId],
        ['table' => 'balance_holds', 'id' => $holdId],
        ['table' => 'transaction_corrections', 'id' => $correctionId],
        ['table' => 'transaction_reversals', 'id' => $reversalId],
    ];

    foreach ($deletions as $deletion) {
        expect(fn (): int => DB::table($deletion['table'])
            ->where('id', $deletion['id'])
            ->delete())->toThrow(QueryException::class);
        expect(DB::table($deletion['table'])->where('id', $deletion['id'])->exists())->toBeTrue();
    }
});
