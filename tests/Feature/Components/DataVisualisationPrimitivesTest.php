<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class DataVisualisationPrimitivesTest extends TestCase
{
    public function test_table_is_semantic_sortable_filterable_and_responsive(): void
    {
        $columns = [
            ['key' => 'select', 'label' => 'Pilih', 'type' => 'checkbox'],
            ['key' => 'reference', 'label' => 'Nomor transaksi', 'sortable' => true, 'sort' => 'ascending'],
            ['key' => 'value', 'label' => 'Nilai', 'numeric' => true],
            ['key' => 'status', 'label' => 'Status', 'type' => 'status'],
            ['key' => 'action', 'label' => 'Tindakan', 'type' => 'action'],
        ];
        $rows = [[
            'id' => 'trx-1',
            'reference' => 'STR-2026-001',
            'value' => 'Rp125.000',
            'status' => ['value' => 'success', 'label' => 'Selesai'],
            'action' => ['label' => 'Lihat transaksi STR-2026-001', 'href' => '/transaksi/1'],
        ]];
        $filters = [
            ['label' => 'Status: Selesai', 'removeHref' => '/transaksi?periode=juli'],
        ];

        $html = Blade::render(<<<'BLADE'
            <x-ui.table caption="Daftar setoran Juli 2026" :columns="$columns" :rows="$rows" :filters="$filters" filter-label="Filter aktif" :sticky="true" mobile-mode="stack" />
        BLADE, compact('columns', 'rows', 'filters'));

        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('<caption', $html);
        self::assertStringContainsString('Daftar setoran Juli 2026', $html);
        self::assertStringContainsString('<thead', $html);
        self::assertStringContainsString('<tbody', $html);
        self::assertStringContainsString('scope="col"', $html);
        self::assertStringContainsString('aria-sort="ascending"', $html);
        self::assertStringContainsString('aria-label="Pilih transaksi STR-2026-001"', $html);
        self::assertStringContainsString('text-right', $html);
        self::assertStringContainsString('amount-tabular', $html);
        self::assertStringContainsString('data-status="success"', $html);
        self::assertStringContainsString('data-lucide="circle-check"', $html);
        self::assertStringContainsString('aria-label="Lihat transaksi STR-2026-001"', $html);
        self::assertStringContainsString('aria-label="Filter aktif"', $html);
        self::assertStringContainsString('Status: Selesai', $html);
        self::assertStringContainsString('aria-label="Hapus filter Status: Selesai"', $html);
        self::assertStringContainsString('sticky', $html);
        self::assertStringContainsString('data-mobile-row-stack', $html);
        self::assertStringContainsString('md:table-row', $html);
    }

    public function test_table_allowlists_sort_status_and_escapes_content_and_urls(): void
    {
        $columns = [['key' => 'name', 'label' => '<script>Kolom</script>', 'sort' => 'sideways']];
        $rows = [['id' => '1', 'name' => '<img src=x onerror=alert(1)>']];
        $filters = [['label' => '<b>Berbahaya</b>', 'removeHref' => 'javascript:alert(1)']];

        $html = Blade::render('<x-ui.table caption="Data aman" :columns="$columns" :rows="$rows" :filters="$filters" />', compact('columns', 'rows', 'filters'));

        self::assertStringNotContainsString('aria-sort="sideways"', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('href="javascript:', $html);
        self::assertStringContainsString('&lt;script&gt;Kolom&lt;/script&gt;', $html);
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
    }

    public function test_pagination_has_result_context_current_page_and_semantic_disabled_controls(): void
    {
        $pages = [
            ['label' => '1', 'url' => '/transaksi?page=1'],
            ['label' => '2', 'url' => '/transaksi?page=2', 'current' => true],
            ['label' => '3', 'url' => '/transaksi?page=3'],
        ];
        $html = Blade::render(<<<'BLADE'
            <x-ui.pagination label="Navigasi halaman transaksi" :current-page="2" :last-page="3" :from="11" :to="20" :total="26" previous-url="/transaksi?page=1" next-url="/transaksi?page=3" :pages="$pages" />
        BLADE, compact('pages'));

        self::assertStringContainsString('<nav', $html);
        self::assertStringContainsString('aria-label="Navigasi halaman transaksi"', $html);
        self::assertStringContainsString('Menampilkan 11–20 dari 26 hasil', $html);
        self::assertStringContainsString('Halaman 2 dari 3', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringContainsString('min-h-touch', $html);
        self::assertStringContainsString('data-page-numbers', $html);
        self::assertStringContainsString('hidden', $html);
        self::assertStringContainsString('sm:flex', $html);

        $disabled = Blade::render('<x-ui.pagination :current-page="1" :last-page="1" :from="0" :to="0" :total="0" />');
        self::assertSame(2, substr_count($disabled, 'aria-disabled="true"'));
        self::assertSame(2, substr_count($disabled, '<span data-pagination-disabled'));
        self::assertStringNotContainsString('tabindex="0"', $disabled);
    }

    public function test_pagination_rejects_unsafe_urls_without_rendering_focusable_links(): void
    {
        $pages = [['label' => '2', 'url' => 'javascript:alert(1)']];
        $html = Blade::render('<x-ui.pagination previous-url="javascript:alert(1)" next-url="data:text/html,bad" :pages="$pages" />', compact('pages'));

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:text', $html);
        self::assertStringNotContainsString('<a', $html);
    }

    public function test_chart_has_complete_context_approved_palette_and_always_visible_data_alternative(): void
    {
        $series = [
            ['label' => 'Plastik', 'tone' => 'forest', 'pattern' => 'solid', 'values' => [['label' => 'Januari', 'value' => 1250, 'formatted' => '1.250 kg']]],
            ['label' => 'Kertas', 'tone' => 'gold', 'pattern' => 'diagonal', 'values' => [['label' => 'Januari', 'value' => 750, 'formatted' => '750 kg']]],
        ];
        $html = Blade::render('<x-ui.chart title="Sampah terkumpul" period="Januari 2026" unit="kilogram" type="bar" :series="$series" summary="Total pengumpulan 2.000 kilogram." />', compact('series'));

        self::assertStringContainsString('<figure', $html);
        self::assertStringContainsString('Sampah terkumpul', $html);
        self::assertStringContainsString('Januari 2026', $html);
        self::assertStringContainsString('Satuan: kilogram', $html);
        self::assertStringContainsString('aria-label="Legenda grafik"', $html);
        self::assertStringContainsString('data-chart-tone="forest"', $html);
        self::assertStringContainsString('data-chart-pattern="diagonal"', $html);
        self::assertStringContainsString('data-chart-interactive="false"', $html);
        self::assertStringContainsString('data-bar-baseline="zero"', $html);
        self::assertStringContainsString('Grafik batang dimulai dari nol.', $html);
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('Ringkasan data grafik', $html);
        self::assertStringContainsString('1.250 kg', $html);
        self::assertStringContainsString('Total pengumpulan 2.000 kilogram.', $html);
        self::assertStringNotContainsString('<svg', $html);
    }

    public function test_chart_allowlists_visual_metadata_and_composes_empty_and_unavailable_states(): void
    {
        $series = [['label' => '<script>Seri</script>', 'tone' => 'neon', 'pattern' => 'hostile', 'values' => []]];
        $empty = Blade::render('<x-ui.chart title="Data bulanan" period="Juli" unit="rupiah" :series="$series" state="empty" />', compact('series'));
        $unavailable = Blade::render('<x-ui.chart title="Data bulanan" period="Juli" unit="kg" state="unavailable" />');

        self::assertStringContainsString('data-chart-tone="forest"', $empty);
        self::assertStringContainsString('data-chart-pattern="solid"', $empty);
        self::assertStringNotContainsString('<script>', $empty);
        self::assertStringContainsString('Belum ada data grafik', $empty);
        self::assertStringContainsString('Data grafik tidak tersedia', $unavailable);
        self::assertStringContainsString('role="status"', $unavailable);
    }

    public function test_qr_display_uses_trusted_image_contract_quiet_zone_and_accessible_fallback_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.qr-display title="QR nasabah" context="Tunjukkan kode ini kepada petugas." image-src="/qr/nasabah-123.png" image-alt="Kode QR nasabah Siti" masked-reference="NSB-****-0123" fallback-number="NSB-0123" download-href="/qr/nasabah-123/download" print-href="/qr/nasabah-123/print" />
        BLADE);

        self::assertStringContainsString('QR nasabah', $html);
        self::assertStringContainsString('Tunjukkan kode ini kepada petugas.', $html);
        self::assertStringContainsString('src="/qr/nasabah-123.png"', $html);
        self::assertStringContainsString('alt="Kode QR nasabah Siti"', $html);
        self::assertStringContainsString('bg-surface', $html);
        self::assertStringContainsString('p-4', $html);
        self::assertStringContainsString('size-[200px]', $html);
        self::assertStringContainsString('max-w-full', $html);
        self::assertStringContainsString('NSB-****-0123', $html);
        self::assertStringContainsString('Nomor alternatif', $html);
        self::assertStringContainsString('NSB-0123', $html);
        self::assertStringContainsString('aria-label="Unduh QR nasabah"', $html);
        self::assertStringContainsString('aria-label="Cetak QR nasabah"', $html);
        self::assertStringContainsString('data-lucide="download"', $html);
        self::assertStringContainsString('data-lucide="printer"', $html);
        self::assertStringNotContainsString('token', strtolower($html));
    }

    public function test_qr_display_rejects_untrusted_sources_markup_urls_and_raw_reference(): void
    {
        $rawReference = 'secret-token-value';
        $html = Blade::render(<<<'BLADE'
            <x-ui.qr-display title="QR verifikasi" context="Pindai untuk verifikasi." image-src="javascript:alert(1)" image-markup="<svg onload=alert(2)></svg>" :masked-reference="$rawReference" fallback-number="VER-0099" download-href="data:text/html,bad" print-href="javascript:alert(3)" />
        BLADE, compact('rawReference'));

        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('data:text', $html);
        self::assertStringNotContainsString('<svg onload', $html);
        self::assertStringNotContainsString($rawReference, $html);
        self::assertStringContainsString('QR belum tersedia', $html);
        self::assertStringContainsString('VER-0099', $html);
        self::assertStringContainsString('data-lucide="download"', Blade::render('<x-ui.qr-display title="QR" context="Aman" fallback-number="1" download-href="/qr/download" />'));
    }

    public function test_every_component_url_surface_rejects_network_control_encoded_and_malformed_values(): void
    {
        $unsafeUrls = [
            '//evil.test/path', '\\\\evil.test\\path', '\\evil.test\\path',
            ' javascript:alert(1)', "\tjavascript:alert(1)", "\0https://safe.test",
            'java%73cript:alert(1)', 'javascript%3Aalert(1)', 'data%3Atext/html,bad',
            'vbscript:msgbox(1)', 'ftp://evil.test/file', 'http:\\evil.test', 'https:evil.test',
            'http://', '://broken',
        ];

        foreach ($unsafeUrls as $url) {
            $columns = [['key' => 'action', 'label' => 'Aksi', 'type' => 'action']];
            $rows = [['id' => '1', 'action' => ['label' => 'Buka', 'href' => $url]]];
            $filters = [['label' => 'Filter', 'removeHref' => $url]];
            $table = Blade::render('<x-ui.table caption="Aman" :columns="$columns" :rows="$rows" :filters="$filters" />', compact('columns', 'rows', 'filters'));
            self::assertStringNotContainsString('<a ', $table, 'Table accepted '.$url);

            $pages = [['label' => '2', 'url' => $url]];
            $pagination = Blade::render('<x-ui.pagination :current-page="1" :last-page="2" :next-url="$url" :pages="$pages" />', compact('url', 'pages'));
            self::assertStringNotContainsString('<a ', $pagination, 'Pagination accepted '.$url);

            $qr = Blade::render('<x-ui.qr-display title="QR" context="Aman" fallback-number="1" :image-src="$url" :download-href="$url" :print-href="$url" />', compact('url'));
            self::assertStringNotContainsString('<img ', $qr, 'QR image accepted '.$url);
            self::assertStringNotContainsString('<a ', $qr, 'QR action accepted '.$url);
        }

        $safeUrls = ['/relatif/path?x=1#bagian', 'relative/path', '?page=2', '#bagian', 'https://example.test/path', 'http://example.test/path'];
        foreach ($safeUrls as $url) {
            $pages = [['label' => '2', 'url' => $url]];
            $html = Blade::render('<x-ui.pagination :current-page="1" :last-page="2" :next-url="$url" :pages="$pages" />', compact('url', 'pages'));
            self::assertStringContainsString('href="'.e($url).'"', $html, 'Safe URL rejected '.$url);
        }
    }

    public function test_every_url_surface_rejects_malformed_percent_encoding_and_preserves_valid_escapes(): void
    {
        $invalidUrls = ['%', '%ZZ', '/%ZZ', '/path%2', 'https://example.test/%GG'];
        foreach ($invalidUrls as $url) {
            $columns = [['key' => 'action', 'label' => 'Aksi', 'type' => 'action']];
            $rows = [['id' => '1', 'action' => ['label' => 'Buka', 'href' => $url]]];
            $filters = [['label' => 'Filter', 'removeHref' => $url]];
            $table = Blade::render('<x-ui.table caption="Aman" :columns="$columns" :rows="$rows" :filters="$filters" />', compact('columns', 'rows', 'filters'));
            self::assertStringNotContainsString('<a ', $table, 'Table accepted malformed percent URL '.$url);

            $pages = [['label' => '2', 'url' => $url]];
            $pagination = Blade::render('<x-ui.pagination :current-page="2" :last-page="3" :previous-url="$url" :next-url="$url" :pages="$pages" />', compact('url', 'pages'));
            self::assertStringNotContainsString('<a ', $pagination, 'Pagination accepted malformed percent URL '.$url);

            $qr = Blade::render('<x-ui.qr-display title="QR" context="Aman" fallback-number="1" :image-src="$url" :download-href="$url" :print-href="$url" />', compact('url'));
            self::assertStringNotContainsString('<img ', $qr, 'QR image accepted malformed percent URL '.$url);
            self::assertStringNotContainsString('<a ', $qr, 'QR action accepted malformed percent URL '.$url);
        }

        $validUrls = [
            '/dokumen%20warga/%E2%9C%93?nama=Siti%20Aminah#bagian%2Futama',
            'relative%2Fpath?redirect=%2Faman',
            'https://example.test/dokumen%20aman?q=%E2%9C%93#hasil%2F1',
        ];
        foreach ($validUrls as $url) {
            $columns = [['key' => 'action', 'label' => 'Aksi', 'type' => 'action']];
            $rows = [['id' => '1', 'action' => ['label' => 'Buka', 'href' => $url]]];
            $filters = [['label' => 'Filter', 'removeHref' => $url]];
            $table = Blade::render('<x-ui.table caption="Aman" :columns="$columns" :rows="$rows" :filters="$filters" />', compact('columns', 'rows', 'filters'));
            self::assertSame(2, substr_count($table, 'href="'.e($url).'"'));

            $pages = [['label' => '2', 'url' => $url]];
            $pagination = Blade::render('<x-ui.pagination :current-page="1" :last-page="2" :next-url="$url" :pages="$pages" />', compact('url', 'pages'));
            self::assertSame(2, substr_count($pagination, 'href="'.e($url).'"'));

            $qr = Blade::render('<x-ui.qr-display title="QR" context="Aman" fallback-number="1" :image-src="$url" :download-href="$url" :print-href="$url" />', compact('url'));
            self::assertStringContainsString('src="'.e($url).'"', $qr);
            self::assertSame(2, substr_count($qr, 'href="'.e($url).'"'));
        }
    }

    public function test_pagination_normalizes_boundaries_ranges_and_current_page_entries(): void
    {
        $pages = [
            ['label' => '0', 'url' => '/page/0', 'current' => true],
            ['label' => '1', 'url' => '/page/1'],
            ['label' => '2', 'url' => '/page/2', 'current' => true],
            ['label' => '9', 'url' => '/page/9', 'current' => true],
        ];
        $first = Blade::render('<x-ui.pagination :current-page="0" :last-page="-4" :from="-8" :to="90" :total="-2" previous-url="/prev" next-url="/next" :pages="$pages" />', compact('pages'));

        self::assertStringContainsString('Halaman 1 dari 1', $first);
        self::assertStringContainsString('Menampilkan 0–0 dari 0 hasil', $first);
        self::assertSame(2, substr_count($first, 'aria-disabled="true"'));
        self::assertSame(1, substr_count($first, 'aria-current="page"'));
        self::assertStringContainsString('>1</span>', $first);
        self::assertStringNotContainsString('href="/prev"', $first);
        self::assertStringNotContainsString('href="/next"', $first);

        $last = Blade::render('<x-ui.pagination :current-page="99" :last-page="3" :from="50" :to="10" :total="26" previous-url="/prev" next-url="/next" :pages="$pages" />', compact('pages'));
        self::assertStringContainsString('Halaman 3 dari 3', $last);
        self::assertStringContainsString('Menampilkan 10–26 dari 26 hasil', $last);
        self::assertStringContainsString('href="/prev"', $last);
        self::assertStringNotContainsString('href="/next"', $last);
        self::assertSame(1, substr_count($last, 'aria-current="page"'));
        self::assertStringContainsString('>3</span>', $last);
    }

    public function test_chart_empty_and_unavailable_states_keep_semantic_alternative_tables(): void
    {
        $empty = Blade::render('<x-ui.chart title="Data" period="Juli" unit="kg" state="empty" />');
        $unavailable = Blade::render('<x-ui.chart title="Data" period="Juli" unit="rupiah" state="unavailable" summary="Sumber sedang diperbarui." />');

        foreach ([$empty, $unavailable] as $html) {
            self::assertStringContainsString('<table', $html);
            self::assertStringContainsString('<caption', $html);
            self::assertStringContainsString('Ringkasan data grafik', $html);
            self::assertStringContainsString('colspan="3"', $html);
        }
        self::assertStringContainsString('Belum ada data untuk periode Juli.', $empty);
        self::assertStringContainsString('Data untuk periode Juli sedang tidak tersedia.', $unavailable);
        self::assertStringContainsString('Sumber sedang diperbarui.', $unavailable);
    }
}
