<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\Payouts\Models\SandboxPayout;
use Illuminate\Support\Facades\Http;
use Throwable;

final class XenditPayoutApi
{
    /** @return array<string, mixed> */
    public function create(SandboxPayout $payout): array
    {
        return $this->call('POST', '/v3/payouts', $payout->request_payload, $payout->provider_key);
    }

    /** @return array<string, mixed> */
    public function find(string $id): array
    {
        if (preg_match('/^po-[A-Za-z0-9-]{1,90}$/D', $id) !== 1) {
            throw new PayoutApiFailure('payout.invalid_provider_id');
        }

        return $this->call('GET', '/v3/payouts/'.$id);
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $payload = [], ?string $key = null): array
    {
        app(PayoutAccess::class)->requireConfigured();
        try {
            $request = Http::withBasicAuth((string) config('payouts.secret_key'), '')->withoutRedirecting()->withHeaders(['api-version' => config('payouts.api_version')])->acceptJson()->connectTimeout(3)->timeout((int) config('payouts.http_timeout'));
            if ($key !== null) {
                $request = $request->withHeaders(['idempotency-key' => $key]);
            }
            $response = $method === 'POST' ? $request->post('https://api.xendit.co'.$path, $payload) : $request->get('https://api.xendit.co'.$path);
            $data = $response->json();
        } catch (Throwable) {
            // Never retain an HTTP exception, auth header, response body, or account number.
            throw new PayoutApiFailure('payout.transport');
        }
        if (! $response->successful() || ! is_array($data)) {
            throw new PayoutApiFailure('payout.http_'.$response->status());
        }

        return $data;
    }
}
