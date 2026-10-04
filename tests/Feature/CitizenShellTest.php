<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CitizenShellTest extends TestCase
{
    /** @var array<string, string> */
    private const DESTINATIONS = [
        'Beranda' => '/dashboard/warga',
        'Setoran' => '/warga/riwayat-setoran',
        'Layanan' => '/warga/penjemputan/ajukan',
        'Kartu Nasabah' => '/warga/kartu-nasabah',
        'Akun' => '/profil/kata-sandi',
    ];

    public function test_layout_renders_complete_indonesian_document_and_landmarks_once(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layouts.citizen title="Beranda" context="Ringkasan akun">
                Isi utama
            </x-layouts.citizen>
        BLADE);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html lang="id">', $html);
        self::assertStringContainsString('<title>Beranda — Bank Sampah Sindangheula</title>', $html);
        self::assertStringContainsString('href="#konten-utama"', $html);
        self::assertStringContainsString('id="konten-utama"', $html);
        self::assertStringContainsString('tabindex="-1"', $html);
        self::assertStringContainsString('max-w-citizen', $html);
        self::assertStringContainsString('pb-[calc(4.5rem+env(safe-area-inset-bottom))]', $html);
        self::assertStringContainsString('Ringkasan akun', $html);
        self::assertSame(1, substr_count($html, '<header'));
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertSame(1, substr_count($html, '<main'));
        $source = file_get_contents(resource_path('views/components/layouts/citizen.blade.php'));
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '@livewireStyles'));
        self::assertSame(1, substr_count($source, '@livewireScripts'));
        self::assertSame(1, substr_count($source, '@vite('));
    }

    public function test_optional_header_content_is_omitted_when_unsupplied_and_actions_are_supported(): void
    {
        $minimal = Blade::render('<x-layouts.citizen title="Beranda">Isi</x-layouts.citizen>');
        $withActions = Blade::render(<<<'BLADE'
            <x-layouts.citizen title="Beranda" context="Konteks aman">
                <x-slot:headerActions><button type="button">Bantuan</button></x-slot:headerActions>
                Isi
            </x-layouts.citizen>
        BLADE);

        self::assertStringNotContainsString('Konteks aman', $minimal);
        self::assertStringNotContainsString('Bantuan', $minimal);
        self::assertStringContainsString('Konteks aman', $withActions);
        self::assertStringContainsString('Bantuan', $withActions);
    }

    public function test_slots_render_in_saldo_first_order_and_unsupplied_slots_are_omitted(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layouts.citizen title="Beranda">
                <x-slot:balance>01-balance</x-slot:balance>
                <x-slot:quickActions>02-quick-actions</x-slot:quickActions>
                <x-slot:activeRequests>03-active-requests</x-slot:activeRequests>
                <x-slot:recentHistory>04-recent-history</x-slot:recentHistory>
                <x-slot:contextualInformation>05-contextual-information</x-slot:contextualInformation>
                06-default
            </x-layouts.citizen>
        BLADE);

        $needles = ['01-balance', '02-quick-actions', '03-active-requests', '04-recent-history', '05-contextual-information', '06-default'];
        $positions = array_map(static fn (string $needle): int|false => strpos($html, $needle), $needles);
        self::assertNotContains(false, $positions);
        foreach (array_slice($positions, 1, null, true) as $index => $position) {
            self::assertGreaterThan($positions[$index - 1], $position);
        }

        $minimal = Blade::render('<x-layouts.citizen title="Beranda">Hanya isi</x-layouts.citizen>');
        foreach (['data-slot-balance', 'data-slot-quick-actions', 'data-slot-active-requests', 'data-slot-recent-history', 'data-slot-contextual-information'] as $marker) {
            self::assertStringNotContainsString($marker, $minimal);
        }
        self::assertStringNotContainsString('Rp', $minimal);
    }

    public function test_navigation_renders_exact_labels_order_destinations_and_one_active_item(): void
    {
        $html = $this->renderNavigation('Beranda');

        self::assertStringContainsString('aria-label="Navigasi warga"', $html);
        self::assertSame(5, substr_count($html, 'data-nav-item'));
        self::assertSame(1, substr_count($html, 'aria-current="page"'));
        self::assertStringContainsString('pb-[env(safe-area-inset-bottom)]', $html);
        self::assertSame(5, substr_count($html, 'min-h-touch'));

        $lastPosition = -1;
        foreach (self::DESTINATIONS as $label => $href) {
            $position = strpos($html, '>'.$label.'</span>');
            self::assertNotFalse($position);
            self::assertGreaterThan($lastPosition, $position);
            self::assertStringContainsString('href="'.$href.'"', $html);
            $lastPosition = $position;
        }
    }

    public function test_each_navigation_destination_can_be_the_only_active_item(): void
    {
        foreach (array_keys(self::DESTINATIONS) as $active) {
            $html = $this->renderNavigation($active);
            self::assertSame(1, substr_count($html, 'aria-current="page"'));
            self::assertMatchesRegularExpression('/href="'.preg_quote(self::DESTINATIONS[$active], '/').'"\s+aria-current="page"/', $html);
        }
    }

    /** @param array<string, string> $destinations */
    #[DataProvider('invalidNavigationProvider')]
    public function test_navigation_rejects_invalid_contract(array $destinations, string $active, string $message): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage($message);

        Blade::render('<x-citizen.navigation :destinations="$destinations" :active="$active" />', compact('destinations', 'active'));
    }

    /** @return iterable<string, array{array<string, string>, string, string}> */
    public static function invalidNavigationProvider(): iterable
    {
        yield 'missing key' => [array_slice(self::DESTINATIONS, 0, 4, true), 'Beranda', 'Citizen navigation destinations must contain exactly: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.'];
        yield 'additional key' => [[...self::DESTINATIONS, 'Lainnya' => '/lainnya'], 'Beranda', 'Citizen navigation destinations must contain exactly: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.'];
        yield 'reordered keys' => [['Setoran' => '/setoran', 'Beranda' => '/', 'Layanan' => '/layanan', 'Riwayat' => '/riwayat', 'Akun' => '/akun'], 'Beranda', 'Citizen navigation destinations must contain exactly: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.'];
        yield 'unknown active' => [self::DESTINATIONS, 'Lainnya', 'Citizen navigation active item must be one of: Beranda, Setoran, Layanan, Kartu Nasabah, Akun.'];
    }

    /** @param array<string, mixed> $destinations */
    #[DataProvider('invalidDestinationValueProvider')]
    public function test_navigation_rejects_unsafe_destination_values(array $destinations, string $message): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage($message);

        Blade::render('<x-citizen.navigation :destinations="$destinations" active="Beranda" />', compact('destinations'));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidDestinationValueProvider(): iterable
    {
        $invalidValues = [
            'integer' => 42,
            'array' => ['/warga'],
            'null' => null,
            'empty' => '',
            'whitespace' => ' /warga',
            'plain relative' => 'warga',
            'http external' => 'http://evil.example',
            'https external' => 'https://evil.example',
            'javascript scheme' => 'javascript:alert(1)',
            'data scheme' => 'data:text/html,test',
            'vbscript scheme' => 'vbscript:msgbox(1)',
            'protocol relative' => '//evil.example',
            'backslash' => '/warga\\akun',
            'UNC' => '\\\\evil.example\\share',
            'control character' => "/warga\nsetoran",
            'encoded javascript scheme' => '%6Aavascript:alert(1)',
            'encoded protocol relative' => '%2F%2Fevil.example',
            'encoded control character' => '/warga%0Asetoran',
            'malformed percent' => '/warga%',
            'malformed percent letters' => '/warga%ZZ',
            'short percent' => '/warga%2',
        ];

        foreach (array_keys(self::DESTINATIONS) as $label) {
            foreach ($invalidValues as $case => $value) {
                $destinations = self::DESTINATIONS;
                $destinations[$label] = $value;
                $message = is_string($value)
                    ? "Citizen navigation destination for {$label} must be a safe internal path, query, or fragment."
                    : "Citizen navigation destination for {$label} must be a string.";

                yield "{$label}: {$case}" => [$destinations, $message];
            }
        }
    }

    /** @param array<string, string> $destinations */
    #[DataProvider('validDestinationValueProvider')]
    public function test_navigation_preserves_and_escapes_safe_internal_destinations(array $destinations, string $expectedHref): void
    {
        $html = Blade::render('<x-citizen.navigation :destinations="$destinations" active="Beranda" />', compact('destinations'));

        self::assertStringContainsString('href="'.$expectedHref.'"', $html);
    }

    /** @return iterable<string, array{array<string, string>, string}> */
    public static function validDestinationValueProvider(): iterable
    {
        $validValues = [
            'encoded space' => ['/warga/ruang%20publik', '/warga/ruang%20publik'],
            'encoded slash' => ['/warga%2Fsetoran', '/warga%2Fsetoran'],
            'UTF-8 path' => ['/warga/riwayat-sampah-éco', '/warga/riwayat-sampah-éco'],
            'UTF-8 query and Blade escaping' => ['?cari=botol-kaca&wilayah=Jogja-ñ', '?cari=botol-kaca&amp;wilayah=Jogja-ñ'],
            'UTF-8 fragment' => ['#riwayat-setoran-日', '#riwayat-setoran-日'],
        ];

        foreach (array_keys(self::DESTINATIONS) as $label) {
            foreach ($validValues as $case => [$value, $expectedHref]) {
                $destinations = self::DESTINATIONS;
                $destinations[$label] = $value;

                yield "{$label}: {$case}" => [$destinations, $expectedHref];
            }
        }
    }

    public function test_components_remain_presentational_and_routes_are_unchanged(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/citizen.blade.php'));
        $navigation = file_get_contents(resource_path('views/components/citizen/navigation.blade.php'));
        self::assertIsString($layout);
        self::assertIsString($navigation);

        $source = $layout.$navigation;
        foreach (['auth(', 'Auth::', 'DB::', 'Model::', 'Livewire\\Component', 'wire:', 'Rp', 'factory(', 'User::'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }

        self::assertSame(['/', 'login'], collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => in_array($route->getName(), ['home', 'login'], true))
            ->map(static fn ($route): string => $route->uri())
            ->values()
            ->all());
    }

    private function renderNavigation(string $active): string
    {
        $destinations = self::DESTINATIONS;

        return Blade::render('<x-citizen.navigation :destinations="$destinations" :active="$active" />', compact('destinations', 'active'));
    }
}
