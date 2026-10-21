<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

it('renders the shared section buttons with their original destinations', function (string $path, string $label, array $labels): void {
    $html = $this->actingAs(User::factory()->create(['role' => 'admin']))->get($path)->assertOk()->getContent();
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $links = $xpath->query('//nav[@aria-label="'.$label.'"]//a');
    expect($links->length)->toBe(count($labels));
    foreach ($labels as $index => $text) {
        $link = $links->item($index);
        expect(preg_replace('/\s+/u', ' ', trim($link->textContent)))->toBe($text)
            ->and($link->getAttribute('class'))->toContain('backoffice-section-link')
            ->and($link->getAttribute('href'))->toStartWith(url('/backoffice/'))
            ->and($link->hasAttribute('role'))->toBeFalse();
    }
    expect($html)->not->toContain('Gunakan tombol Tab untuk memfokuskan navigasi');
})->with([
    'catalog' => ['/backoffice/waste-catalog', 'Bagian katalog sampah', ['Kategori', 'Jenis', 'Kondisi', 'Harga', 'Satuan']],
    'regions' => ['/backoffice/regions', 'Bagian wilayah', ['Area pelayanan', 'Dusun', 'RW', 'RT']],
    'directory' => ['/backoffice/directory', 'Bagian direktori', ['Nasabah', 'Pengguna & peran', 'Verifikasi warga']],
]);

it('keeps active sections semantic and visibly marked without adding fake tabs', function (): void {
    $html = Blade::render(<<<'BLADE'
        <x-backoffice.section-navigation label="Bagian uji">
            <x-backoffice.section-link href="/aktif" icon="heroicon-o-tag" :active="true">Aktif</x-backoffice.section-link>
            <x-backoffice.section-link href="/lain" icon="heroicon-o-map">Lain</x-backoffice.section-link>
        </x-backoffice.section-navigation>
        BLADE);
    expect(substr_count($html, 'aria-current="page"'))->toBe(1)
        ->and(substr_count($html, '<svg'))->toBe(3)
        ->and($html)->toContain('ml-auto size-5')
        ->not->toContain('role="tab', 'tabindex="0"', 'overflow-x-auto');
});
