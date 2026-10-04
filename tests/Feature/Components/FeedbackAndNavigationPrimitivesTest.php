<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Tests\TestCase;

final class FeedbackAndNavigationPrimitivesTest extends TestCase
{
    public function test_status_badge_uses_allowlisted_status_with_text_icon_and_colour(): void
    {
        $success = Blade::render('<x-ui.status-badge status="success">Selesai</x-ui.status-badge>');
        $fallback = Blade::render('<x-ui.status-badge status="unknown">Status tidak dikenal</x-ui.status-badge>');

        self::assertStringContainsString('data-status="success"', $success);
        self::assertStringContainsString('bg-success-bg', $success);
        self::assertStringContainsString('data-lucide="circle-check"', $success);
        self::assertStringContainsString('Selesai', $success);
        self::assertStringContainsString('data-status="pending"', $fallback);
        self::assertStringContainsString('data-lucide="clock-3"', $fallback);
    }

    public function test_panel_renders_flat_semantic_surface_and_relevant_states(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.panel title="Ringkasan" description="Data terbaru" state="error" :loading="true">
                Isi panel
                <x-slot:actions><button type="button">Coba lagi</button></x-slot:actions>
            </x-ui.panel>
        BLADE);

        self::assertStringContainsString('<section', $html);
        self::assertStringContainsString('aria-labelledby="panel-', $html);
        self::assertStringContainsString('aria-describedby="panel-', $html);
        self::assertStringContainsString('aria-busy="true"', $html);
        self::assertStringContainsString('border-terracotta', $html);
        self::assertStringContainsString('Ringkasan', $html);
        self::assertStringContainsString('Coba lagi', $html);
    }

    public function test_app_header_exposes_landmark_title_context_and_touch_targets(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.app-header title="Riwayat" context="Akun Siti" back-href="/beranda">
                <x-slot:actions><button type="button">Notifikasi</button></x-slot:actions>
            </x-ui.app-header>
        BLADE);

        self::assertStringContainsString('<header', $html);
        self::assertStringContainsString('min-h-touch', $html);
        self::assertStringContainsString('href="/beranda"', $html);
        self::assertStringContainsString('aria-label="Kembali"', $html);
        self::assertStringContainsString('data-lucide="arrow-left"', $html);
        self::assertStringContainsString('Riwayat', $html);
        self::assertStringContainsString('Akun Siti', $html);
    }

    public function test_bottom_navigation_limits_items_and_marks_active_item_semantically(): void
    {
        $items = [
            ['label' => 'Beranda', 'href' => '/beranda', 'icon' => 'home', 'active' => true],
            ['label' => 'Setoran', 'href' => '/setoran', 'icon' => 'recycle'],
            ['label' => 'Layanan', 'href' => '/layanan', 'icon' => 'grid-2x2', 'badge' => '2'],
            ['label' => 'Riwayat', 'href' => '/riwayat', 'icon' => 'history'],
            ['label' => 'Akun', 'href' => '/akun', 'icon' => 'user-round'],
        ];
        $html = Blade::render('<x-ui.bottom-navigation :items="$items" label="Navigasi utama" />', compact('items'));

        self::assertStringContainsString('<nav', $html);
        self::assertStringContainsString('aria-label="Navigasi utama"', $html);
        self::assertSame(5, substr_count($html, 'data-nav-item'));
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertStringNotContainsString('data-active-indicator', $html);
        self::assertStringContainsString('font-semibold text-forest-600', $html);
        self::assertStringContainsString('min-h-touch', $html);
        self::assertStringContainsString('aria-label="2 notifikasi"', $html);

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Bottom navigation supports at most five items.');
        Blade::render('<x-ui.bottom-navigation :items="$items" />', ['items' => [...$items, $items[0]]]);
    }

    public function test_bottom_sheet_is_an_alpine_ready_labelled_modal_with_close_controls(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.bottom-sheet id="bottom-sheet-filter" name="filter" title="Pilih filter" description="Batasi daftar transaksi." :open="true">
                Isi sheet
            </x-ui.bottom-sheet>
        BLADE);

        self::assertStringContainsString('x-data=', $html);
        self::assertStringContainsString('x-trap.inert.noscroll="open"', $html);
        self::assertStringContainsString('x-on:keydown.escape.window="closeModal()"', $html);
        self::assertStringContainsString('role="dialog"', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('aria-labelledby="bottom-sheet-filter-title"', $html);
        self::assertStringContainsString('aria-describedby="bottom-sheet-filter-description"', $html);
        self::assertStringContainsString('aria-label="Tutup pilih filter"', $html);
        self::assertStringContainsString('data-initial-focus', $html);
    }

    public function test_dialog_is_an_alpine_ready_labelled_modal_with_stateful_actions(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.dialog id="dialog-confirm-payment" name="confirm-payment" title="Konfirmasi pembayaran" description="Pastikan nominal sudah benar." state="error" :open="true">
                Pembayaran gagal diproses.
                <x-slot:actions><button type="button" disabled>Memproses</button></x-slot:actions>
            </x-ui.dialog>
        BLADE);

        self::assertStringContainsString('x-trap.inert.noscroll="open"', $html);
        self::assertStringContainsString('role="dialog"', $html);
        self::assertStringContainsString('aria-modal="true"', $html);
        self::assertStringContainsString('aria-labelledby="dialog-confirm-payment-title"', $html);
        self::assertStringContainsString('aria-describedby="dialog-confirm-payment-description"', $html);
        self::assertStringContainsString('aria-label="Tutup konfirmasi pembayaran"', $html);
        self::assertStringContainsString('border-terracotta', $html);
        self::assertStringContainsString('disabled', $html);
    }

    public function test_duplicate_instances_receive_unique_consistent_ids_and_allow_overrides(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.panel title="Sama" description="Sama">Satu</x-ui.panel>
            <x-ui.panel title="Sama" description="Sama">Dua</x-ui.panel>
            <x-ui.dialog name="same" title="Sama" description="Sama">Satu</x-ui.dialog>
            <x-ui.dialog name="same" title="Sama" description="Sama">Dua</x-ui.dialog>
            <x-ui.bottom-sheet name="same" title="Sama" description="Sama">Satu</x-ui.bottom-sheet>
            <x-ui.bottom-sheet name="same" title="Sama" description="Sama">Dua</x-ui.bottom-sheet>
        BLADE);

        preg_match_all('/aria-labelledby="([^"]+)"/', $html, $labelledBy);
        self::assertCount(6, $labelledBy[1]);
        self::assertCount(6, array_unique($labelledBy[1]));
        foreach ($labelledBy[1] as $id) {
            self::assertSame(1, substr_count($html, 'id="'.$id.'"'));
        }

        $overrides = Blade::render(<<<'BLADE'
            <x-ui.panel id="custom-panel" title="Khusus">Isi</x-ui.panel>
            <x-ui.dialog id="custom-dialog" name="custom" title="Khusus">Isi</x-ui.dialog>
            <x-ui.bottom-sheet id="custom-sheet" name="custom" title="Khusus">Isi</x-ui.bottom-sheet>
        BLADE);
        self::assertStringContainsString('aria-labelledby="custom-panel-title"', $overrides);
        self::assertStringContainsString('aria-labelledby="custom-dialog-title"', $overrides);
        self::assertStringContainsString('aria-labelledby="custom-sheet-title"', $overrides);
    }

    public function test_dialog_and_sheet_share_complete_focus_lifecycle_hooks(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.dialog name="focus" title="Dialog fokus" :open="true">Isi</x-ui.dialog>
            <x-ui.bottom-sheet name="focus" title="Sheet fokus" :open="true">Isi</x-ui.bottom-sheet>
        BLADE);

        self::assertSame(2, substr_count($html, 'invoker: null'));
        self::assertSame(2, substr_count($html, 'openModal(invoker = document.activeElement) {'));
        self::assertSame(4, substr_count($html, 'invoker instanceof HTMLElement'));
        self::assertSame(4, substr_count($html, 'typeof invoker.focus === \'function\''));
        self::assertSame(4, substr_count($html, 'invoker.isConnected'));
        self::assertSame(2, substr_count($html, 'this.invoker = invoker'));
        self::assertSame(6, substr_count($html, 'focusInitial()'));
        self::assertSame(2, substr_count($html, 'this.$nextTick(() => this.$refs.initialFocus.focus())'));
        self::assertSame(2, substr_count($html, 'closeModal() {'));
        self::assertSame(2, substr_count($html, 'const invoker = this.invoker'));
        self::assertSame(2, substr_count($html, 'this.invoker = null'));
        self::assertSame(2, substr_count($html, 'open ? focusInitial() : null'));
        self::assertStringNotContainsString('x-init="if (open) openModal()"', $html);
        self::assertSame(2, substr_count($html, 'x-on:keydown.escape.window="closeModal()"'));
        self::assertSame(4, substr_count($html, 'x-on:click="closeModal()"'));
        self::assertSame(2, substr_count($html, 'x-ref="initialFocus"'));
        self::assertStringContainsString('x-on:open-dialog.window=', $html);
        self::assertStringContainsString('x-on:open-bottom-sheet.window=', $html);
        self::assertSame(2, substr_count($html, '$event.detail?.id ==='));
        self::assertSame(2, substr_count($html, 'openModal($event.detail?.invoker)'));
    }

    public function test_all_status_badge_toast_panel_and_dialog_states_map_semantically(): void
    {
        $badges = [
            'pending' => ['clock-3', 'bg-warning-bg'],
            'in_progress' => ['loader-circle', 'bg-info-bg'],
            'success' => ['circle-check', 'bg-success-bg'],
            'error' => ['circle-x', 'bg-danger-bg'],
            'cancelled' => ['ban', 'bg-disabled-bg'],
            'expired' => ['timer-off', 'bg-disabled-bg'],
        ];
        foreach ($badges as $status => [$icon, $colour]) {
            $html = Blade::render('<x-ui.status-badge :status="$status">Status</x-ui.status-badge>', compact('status'));
            self::assertStringContainsString('data-status="'.$status.'"', $html);
            self::assertStringContainsString('data-lucide="'.$icon.'"', $html);
            self::assertStringContainsString($colour, $html);
        }

        foreach (['info', 'success', 'warning'] as $type) {
            $html = Blade::render('<x-ui.toast :type="$type" title="Info">Isi</x-ui.toast>', compact('type'));
            self::assertStringContainsString('role="status"', $html);
            self::assertStringContainsString('aria-live="polite"', $html);
        }
        $errorToast = Blade::render('<x-ui.toast type="error" title="Gagal">Isi</x-ui.toast>');
        self::assertStringContainsString('role="alert"', $errorToast);
        self::assertStringContainsString('aria-live="assertive"', $errorToast);

        $stateClasses = ['default' => 'border-border', 'error' => 'border-terracotta', 'success' => 'border-forest-600', 'disabled' => 'bg-disabled-bg'];
        foreach ($stateClasses as $state => $class) {
            $panel = Blade::render('<x-ui.panel :state="$state" title="Panel">Isi</x-ui.panel>', compact('state'));
            self::assertStringContainsString($class, $panel);
        }
        foreach (array_slice($stateClasses, 0, 3, true) as $state => $class) {
            $dialog = Blade::render('<x-ui.dialog name="state" title="Dialog" :state="$state">Isi</x-ui.dialog>', compact('state'));
            self::assertStringContainsString($class, $dialog);
        }
    }

    public function test_bottom_navigation_rejects_multiple_active_items(): void
    {
        $items = [
            ['label' => 'Beranda', 'href' => '/', 'icon' => 'home', 'active' => true],
            ['label' => 'Riwayat', 'href' => '/riwayat', 'icon' => 'history', 'active' => true],
        ];

        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('Bottom navigation supports at most one active item.');
        Blade::render('<x-ui.bottom-navigation :items="$items" />', compact('items'));
    }

    public function test_toast_uses_urgency_appropriate_live_region_and_escaped_content(): void
    {
        $success = Blade::render('<x-ui.toast type="success" title="Berhasil">Setoran tersimpan.</x-ui.toast>');
        $error = Blade::render('<x-ui.toast type="error" title="Gagal" :dismissible="false">{{ $message }}</x-ui.toast>', [
            'message' => '<script>alert(1)</script>',
        ]);

        self::assertStringContainsString('role="status"', $success);
        self::assertStringContainsString('aria-live="polite"', $success);
        self::assertStringContainsString('data-lucide="circle-check"', $success);
        self::assertStringContainsString('aria-label="Tutup notifikasi"', $success);
        self::assertStringContainsString('role="alert"', $error);
        self::assertStringContainsString('aria-live="assertive"', $error);
        self::assertStringContainsString('data-lucide="circle-alert"', $error);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $error);
        self::assertStringNotContainsString('<script>', $error);
        self::assertStringNotContainsString('aria-label="Tutup notifikasi"', $error);
    }
}
