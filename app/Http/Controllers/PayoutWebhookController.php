<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PayoutWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(PayoutAccess::configured(), 404);
        abort_unless(hash_equals((string) config('payouts.webhook_token'), (string) $request->header('x-callback-token')), 403);
        abort_if(strlen($request->getContent()) > 128 * 1024, 413);
        $payload = $request->validate([
            'event' => ['required', 'in:v3_payout.succeeded,v3_payout.failed,v3_payout.reversed,v3_payout.rejected,v3_payout.pending_compliance'],
            'business_id' => ['required', 'string', 'max:100'], 'created' => ['required', 'date'],
            'data.payout_id' => ['required', 'string', 'regex:/^po-[A-Za-z0-9-]{1,90}$/D'],
            'data.reference_id' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($payload): void {
            $row = SandboxPayout::query()->where('reference_id', $payload['data']['reference_id'])->lockForUpdate()->first();
            abort_unless($row !== null, 404);
            abort_unless(($row->provider_id === null || $row->provider_id === $payload['data']['payout_id']) && ($row->business_id === null || $row->business_id === $payload['business_id']), 422);
            if ($row->status === 'cancelled' && $row->provider_id === null) {
                return;
            }
            $key = hash('sha256', json_encode([$payload['event'], $payload['data']['payout_id'], $payload['created']], JSON_THROW_ON_ERROR));
            $inserted = DB::table('sandbox_payout_events')->insertOrIgnore([
                'sandbox_payout_id' => $row->id, 'dedupe_key' => $key, 'event' => $payload['event'],
                'provider_id' => $payload['data']['payout_id'], 'created_at' => now(),
            ]);
            if ($inserted > 0) {
                $row->forceFill(['provider_id' => $payload['data']['payout_id'], 'next_check_at' => now()])->save();
            }
        }, 5);

        // Reconcile via authenticated GET outside the callback and outside financial locks.
        return response()->json(['ok' => true]);
    }
}
