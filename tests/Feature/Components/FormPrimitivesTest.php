<?php

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class FormPrimitivesTest extends TestCase
{
    public function test_button_renders_primary_and_loading_states(): void
    {
        $primary = Blade::render('<x-ui.button>Simpan</x-ui.button>');
        $loading = Blade::render('<x-ui.button :loading="true" loading-text="Menyimpan">Simpan</x-ui.button>');

        self::assertStringContainsString('bg-forest-600', $primary);
        self::assertStringContainsString('Simpan', $primary);
        self::assertStringContainsString('aria-busy="true"', $loading);
        self::assertStringContainsString('disabled="disabled"', $loading);
        self::assertStringContainsString('Menyimpan', $loading);
        self::assertStringNotContainsString('>Simpan<', $loading);
    }

    public function test_input_connects_label_hint_and_error_accessibly(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.input
                name="phone"
                label="Nomor telepon"
                hint="Gunakan nomor WhatsApp aktif."
                error="Nomor telepon wajib diisi."
                autocomplete="tel"
                required
            />
        BLADE);

        self::assertStringContainsString('for="phone"', $html);
        self::assertStringContainsString('id="phone"', $html);
        self::assertStringContainsString('aria-describedby="phone-hint phone-error"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('autocomplete="tel"', $html);
        self::assertStringContainsString('required="required"', $html);
    }

    public function test_select_and_textarea_render_labels_and_content(): void
    {
        $select = Blade::render(<<<'BLADE'
            <x-ui.select name="waste_type" label="Jenis sampah">
                <option value="pet">Botol plastik PET</option>
            </x-ui.select>
        BLADE);
        $textarea = Blade::render(<<<'BLADE'
            <x-ui.textarea name="notes" label="Catatan" disabled>Jemput di depan rumah.</x-ui.textarea>
        BLADE);

        self::assertStringContainsString('name="waste_type"', $select);
        self::assertStringContainsString('Botol plastik PET', $select);
        self::assertStringContainsString('for="notes"', $textarea);
        self::assertStringContainsString('disabled="disabled"', $textarea);
        self::assertStringContainsString('Jemput di depan rumah.', $textarea);
    }

    public function test_upload_declares_accepted_types_and_error_relationship(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.upload name="proof" label="Bukti transaksi" error="Bukti wajib diunggah." required />
        BLADE);

        self::assertStringContainsString('type="file"', $html);
        self::assertStringContainsString('accept="image/jpeg,image/png,image/webp,application/pdf"', $html);
        self::assertStringContainsString('aria-describedby="proof-hint proof-error"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('required="required"', $html);
    }

    public function test_amount_display_exposes_label_value_helper_and_recency(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.amount-display
                label="Saldo tersedia"
                amount="Rp125.000"
                helper="Saldo tertahan Rp25.000"
                updated-at="hari ini, 10.30 WIB"
            />
        BLADE);

        self::assertStringContainsString('Saldo tersedia', $html);
        self::assertStringContainsString('Rp125.000', $html);
        self::assertStringContainsString('amount-tabular', $html);
        self::assertStringContainsString('Saldo tertahan Rp25.000', $html);
        self::assertStringContainsString('Diperbarui hari ini, 10.30 WIB', $html);
    }
}
