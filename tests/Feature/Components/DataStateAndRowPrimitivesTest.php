<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class DataStateAndRowPrimitivesTest extends TestCase
{
    public function test_skeleton_is_visual_only_static_under_reduced_motion_and_can_report_long_loading(): void
    {
        $html = Blade::render('<x-ui.skeleton :lines="3" label="Memuat transaksi" :delayed="true" />');

        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('Memuat transaksi', $html);
        self::assertStringContainsString('aria-hidden="true"', $html);
        self::assertSame(3, substr_count($html, 'data-skeleton-line'));
        self::assertStringContainsString('motion-reduce:animate-none', $html);
        self::assertStringNotContainsString('shimmer', strtolower($html));
        self::assertStringContainsString('Pemuatan membutuhkan waktu lebih lama.', $html);
    }

    public function test_empty_state_distinguishes_absence_from_filter_results_and_allows_one_action(): void
    {
        $empty = Blade::render(<<<'BLADE'
            <x-ui.empty-state kind="no-data" title="Belum ada transaksi" description="Transaksi pertama akan tampil di sini." action-label="Buat setoran" action-href="/setoran" />
        BLADE);
        $filtered = Blade::render('<x-ui.empty-state kind="no-results" title="Tidak ada hasil" description="Ubah atau hapus filter." />');

        self::assertStringContainsString('data-empty-kind="no-data"', $empty);
        self::assertStringContainsString('data-lucide="inbox"', $empty);
        self::assertStringContainsString('href="/setoran"', $empty);
        self::assertSame(1, substr_count($empty, 'data-empty-action'));
        self::assertStringContainsString('data-empty-kind="no-results"', $filtered);
        self::assertStringContainsString('data-lucide="search-x"', $filtered);
    }

    public function test_error_state_is_focusable_alert_and_preserves_surrounding_form_content(): void
    {
        $message = '<script>alert(1)</script>';
        $html = Blade::render(<<<'BLADE'
            <form><input name="amount" value="25000"><x-ui.error-state title="Transaksi gagal" :message="$message" action-label="Coba lagi" /></form>
        BLADE, compact('message'));

        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('tabindex="-1"', $html);
        self::assertStringContainsString('data-error-focus', $html);
        self::assertStringContainsString('x-ref="errorSummary"', $html);
        self::assertStringContainsString('x-on:focus-error-summary.window=', $html);
        self::assertStringContainsString('$event.detail?.id ===', $html);
        self::assertStringContainsString('$nextTick(() => $refs.errorSummary.focus())', $html);
        self::assertStringNotContainsString('.reset(', $html);
        self::assertStringNotContainsString('form.reset', $html);
        self::assertStringContainsString('data-lucide="circle-alert"', $html);
        self::assertStringContainsString('value="25000"', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);

        $hostileId = "error-'\\</section><script>alert(2)</script>";
        $hostile = Blade::render('<x-ui.error-state :id="$hostileId" title="Gagal" message="Tetap aman" />', compact('hostileId'));
        self::assertStringContainsString('id="error-&#039;\\', $hostile);
        self::assertStringContainsString('error-\\u0027', $hostile);
        self::assertStringContainsString('\\u003C\\/section\\u003E', $hostile);
        self::assertStringContainsString('\\u003Cscript\\u003Ealert(2)\\u003C\\/script\\u003E', $hostile);
        self::assertStringNotContainsString("=== 'error-'", $hostile);
        self::assertStringNotContainsString('<script>alert(2)</script>', $hostile);
    }

    public function test_success_state_renders_durable_financial_receipt_and_two_named_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.success-state title="Setoran berhasil" reference="STR-2026-001" value="Rp125.000" time="29 Juli 2026, 10.15" status="success" view-href="/bukti/1" print-href="/bukti/1/cetak" />
        BLADE);

        self::assertStringContainsString('data-success-receipt', $html);
        self::assertStringContainsString('data-lucide="circle-check"', $html);
        self::assertStringContainsString('STR-2026-001', $html);
        self::assertStringContainsString('Rp125.000', $html);
        self::assertStringContainsString('amount-tabular', $html);
        self::assertStringContainsString('29 Juli 2026, 10.15', $html);
        self::assertStringContainsString('data-status="success"', $html);
        self::assertStringContainsString('Lihat bukti', $html);
        self::assertStringContainsString('Cetak bukti', $html);
    }

    public function test_timeline_is_semantic_marks_current_and_blocks_success_after_failure(): void
    {
        $steps = [
            ['title' => 'Diajukan', 'status' => 'success', 'time' => '09.00'],
            ['title' => 'Ditolak', 'status' => 'error', 'time' => '09.30', 'note' => 'Data tidak sesuai'],
            ['title' => 'Dibayar', 'status' => 'success', 'time' => '10.00'],
        ];
        $html = Blade::render('<x-ui.timeline :steps="$steps" :current="1" label="Riwayat pencairan" />', compact('steps'));

        self::assertStringContainsString('<ol', $html);
        self::assertStringContainsString('aria-label="Riwayat pencairan"', $html);
        self::assertSame(1, substr_count($html, 'aria-current="step"'));
        self::assertStringContainsString('data-status="error"', $html);
        self::assertStringContainsString('data-status="pending"', $html);
        self::assertSame(1, substr_count($html, 'data-status="success"'));
    }

    public function test_timeline_normalizes_invalid_status_consistently_before_failure_boundary(): void
    {
        $steps = [
            ['title' => 'Status eksternal', 'status' => 'not-a-status'],
            ['title' => 'Ditolak', 'status' => 'error'],
            ['title' => 'Tidak boleh selesai', 'status' => 'success'],
        ];
        $html = Blade::render('<x-ui.timeline :steps="$steps" />', compact('steps'));

        self::assertSame(2, substr_count($html, 'data-status="pending"'));
        self::assertSame(2, substr_count($html, '>pending</span>'));
        self::assertSame(1, substr_count($html, 'data-status="error"'));
        self::assertSame(1, substr_count($html, '>error</span>'));
        self::assertStringNotContainsString('data-status="success"', $html);
        self::assertStringNotContainsString('>not-a-status</span>', $html);
    }

    public function test_timeline_supports_correction_before_after_and_balance_impact(): void
    {
        $steps = [[
            'title' => 'Koreksi nilai', 'status' => 'success', 'before' => 'Rp100.000',
            'after' => 'Rp90.000', 'balanceImpact' => '-Rp10.000',
        ]];
        $html = Blade::render('<x-ui.timeline :steps="$steps" />', compact('steps'));

        self::assertStringContainsString('Sebelum', $html);
        self::assertStringContainsString('Sesudah', $html);
        self::assertStringContainsString('Dampak saldo', $html);
        self::assertStringContainsString('amount-tabular', $html);
    }

    public function test_transaction_row_is_safe_wrapping_tabular_and_explicit_about_correction(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.transaction-row type="Setoran koreksi dengan nama sangat panjang" reference="STR-2026-07-29-REFERENSI-SANGAT-PANJANG" status="success" status-label="Selesai" time="29 Juli 2026 pukul 10.30 WIB" method="Penimbangan langsung di lokasi layanan keliling Desa Sindangheula" weight="12.345.678,90 kilogram" value="Rp9.999.999.999.999" href="/transaksi/1" :corrected="true" />
        BLADE);

        self::assertStringContainsString('min-h-18', $html);
        self::assertStringContainsString('min-w-0', $html);
        self::assertStringNotContainsString('break-all', $html);
        self::assertStringContainsString('data-transaction-type', $html);
        self::assertStringContainsString('data-transaction-reference', $html);
        self::assertStringContainsString('break-words', $html);
        self::assertStringContainsString('[overflow-wrap:anywhere]', $html);
        self::assertStringContainsString('Setoran koreksi dengan nama sangat panjang', $html);
        self::assertStringContainsString('STR-2026-07-29-REFERENSI-SANGAT-PANJANG', $html);
        self::assertStringContainsString('Rp9.999.999.999.999', $html);
        self::assertStringContainsString('12.345.678,90 kilogram', $html);
        self::assertStringContainsString('flex-col', $html);
        self::assertStringContainsString('sm:flex-row', $html);
        self::assertStringContainsString('self-start', $html);
        self::assertStringContainsString('max-w-full', $html);
        self::assertStringContainsString('amount-tabular', $html);
        self::assertStringContainsString('Dikoreksi', $html);
        self::assertSame(1, substr_count($html, '<a '));
        self::assertStringNotContainsString('<button', $html);
    }

    public function test_task_row_has_one_visible_action_and_group_count_context(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.task-row type="Penjemputan sampah terpilah" subject="Siti Aminah — Kampung Cibiru Hilir RT 004 RW 009 dekat balai pertemuan warga" due="Hari ini, pukul 10.30 WIB sebelum layanan keliling ditutup" status="pending" status-label="Belum dimulai dan menunggu petugas" action-label="Mulai tugas" action-href="/tugas/1" group="Belum dimulai untuk wilayah Sindangheula bagian selatan" :count="4" />
        BLADE);

        self::assertStringContainsString('min-h-16', $html);
        self::assertStringNotContainsString('max-h-20', $html);
        self::assertStringNotContainsString('truncate', $html);
        self::assertStringContainsString('min-h-16', $html);
        self::assertStringContainsString('sm:flex-row', $html);
        self::assertStringContainsString('line-clamp-2', $html);
        self::assertStringContainsString('aria-label="Penjemputan sampah terpilah untuk Siti Aminah', $html);
        self::assertStringContainsString('title="Siti Aminah', $html);
        self::assertStringContainsString('Belum dimulai', $html);
        self::assertStringContainsString('4 tugas', $html);
        self::assertStringContainsString('data-task-action', $html);
        self::assertSame(1, substr_count($html, '<a '));
        self::assertStringNotContainsString('swipe', strtolower($html));
    }
}
