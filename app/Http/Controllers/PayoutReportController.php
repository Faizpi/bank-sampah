<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutReport;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PayoutReportController
{
    public function __invoke(Request $request, PayoutReport $reports): StreamedResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);
        $rows = $reports->query($actor, $request->only(['start', 'end', 'search']))->latest('id')->limit(1000)->get();
        $channels = app(PayoutChannels::class)->all();

        return response()->streamDownload(static function () use ($rows, $channels): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Nomor', 'Warga', 'Tujuan', 'Nomor tersamarkan', 'Nominal (Rp)', 'Status', 'ID provider', 'Lingkungan', 'Pelaksana'], ',', '"', '');
            foreach ($rows as $row) {
                $values = [$row->request_number, $row->customer->name, $channels[$row->channel]['name'], $row->maskedNumber(), (string) $row->amount, $row->label(), $row->provider_id ?? '', 'Sandbox (tidak mengirim dana riil)', 'Sistem otomatis'];
                $safe = array_map(static fn (string $value): string => preg_match('/^[\s]*[=+@-]/u', $value) === 1 ? "'".$value : $value, $values);
                fputcsv($stream, $safe, ',', '"', '');
            }
            fclose($stream);
        }, 'pencairan-digital-simulasi.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
