<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class OfficerShellTest extends TestCase
{
    /** @var array<string, string> */
    private const OFFICER_DESTINATIONS = [
        'Tugas' => '/dashboard/petugas',
        'Setoran' => '/petugas/pindai',
        'Layanan' => '/petugas/layanan-keliling',
        'Akun' => '/profil/kata-sandi',
    ];

    /** @var array<string, string> */
    private const TREASURER_DESTINATIONS = [
        'Tugas' => '/dashboard/bendahara',
        'Pembayaran' => '/bendahara/pencairan',
        'Laporan' => '/bendahara/laporan',
        'Akun' => '/profil/kata-sandi',
    ];

    public function test_layout_renders_complete_indonesian_task_first_document_once(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layouts.officer title="Tugas hari ini">
                Isi utama
            </x-layouts.officer>
        BLADE);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('<html lang="id">', $html);
        self::assertStringContainsString('<title>Tugas hari ini</title>', $html);
        self::assertStringContainsString('href="#konten-utama"', $html);
        self::assertStringContainsString('id="konten-utama"', $html);
        self::assertStringContainsString('tabindex="-1"', $html);
        self::assertStringContainsString('max-w-officer', $html);
        self::assertStringContainsString('pb-[calc(5.75rem+env(safe-area-inset-bottom))]', $html);
        self::assertSame(1, substr_count($html, '<header'));
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertSame(1, substr_count($html, '<main'));

        $source = file_get_contents(resource_path('views/components/layouts/officer.blade.php'));
        self::assertIsString($source);
        self::assertSame(1, substr_count($source, '@livewireStyles'));
        self::assertSame(1, substr_count($source, '@livewireScripts'));
        self::assertSame(1, substr_count($source, '@vite('));
    }

    public function test_header_preserves_long_page_title_without_visual_truncation(): void
    {
        $title = 'Tugas penjemputan sampah terpilah wilayah Sindangheula bagian selatan hari ini';
        $html = Blade::render('<x-layouts.officer :title="$title">Isi</x-layouts.officer>', compact('title'));

        self::assertStringContainsString($title, $html);
        self::assertMatchesRegularExpression('/<h1[^>]*class="[^"]*break-words[^"]*"[^>]*>'.$title.'<\/h1>/', $html);
        self::assertDoesNotMatchRegularExpression('/<h1[^>]*class="[^"]*truncate[^"]*"/', $html);
    }

    public function test_header_optional_slots_are_caller_owned_visible_and_omitted_when_absent(): void
    {
        $minimal = Blade::render('<x-layouts.officer title="Tugas">Isi</x-layouts.officer>');
        $complete = Blade::render(<<<'BLADE'
            <x-layouts.officer title="Tugas">
                <x-slot:date>30 Juli 2026</x-slot:date>
                <x-slot:location>Dusun Sindangheula</x-slot:location>
                <x-slot:connectivity><strong>Koneksi terbatas</strong></x-slot:connectivity>
                <x-slot:profile><button type="button">Profil petugas</button></x-slot:profile>
                Isi
            </x-layouts.officer>
        BLADE);

        foreach (['30 Juli 2026', 'Dusun Sindangheula', 'Koneksi terbatas', 'Profil petugas'] as $content) {
            self::assertStringNotContainsString($content, $minimal);
            self::assertStringContainsString($content, $complete);
        }
        self::assertStringContainsString('<strong>Koneksi terbatas</strong>', $complete);
        foreach (['Hari ini', 'Online', 'Offline', 'Lokasi'] as $inferred) {
            self::assertStringNotContainsString($inferred, $minimal);
        }
    }

    public function test_slots_render_in_task_first_order_and_absent_wrappers_are_omitted(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-layouts.officer title="Tugas">
                <x-slot:todayTasks>01-today-tasks</x-slot:todayTasks>
                <x-slot:taskActions>02-task-actions</x-slot:taskActions>
                <x-slot:operationalContext>03-operational-context</x-slot:operationalContext>
                <x-slot:recentActivity>04-recent-activity</x-slot:recentActivity>
                05-default
            </x-layouts.officer>
        BLADE);

        $needles = ['01-today-tasks', '02-task-actions', '03-operational-context', '04-recent-activity', '05-default'];
        $positions = array_map(static fn (string $needle): int|false => strpos($html, $needle), $needles);
        self::assertNotContains(false, $positions);
        foreach (array_slice($positions, 1, null, true) as $index => $position) {
            self::assertGreaterThan($positions[$index - 1], $position);
        }

        $minimal = Blade::render('<x-layouts.officer title="Tugas">Hanya isi</x-layouts.officer>');
        foreach (['data-slot-today-tasks', 'data-slot-task-actions', 'data-slot-operational-context', 'data-slot-recent-activity'] as $marker) {
            self::assertStringNotContainsString($marker, $minimal);
        }
    }

    /** @param array<string, string> $destinations */
    #[DataProvider('validNavigationProvider')]
    public function test_navigation_supports_persona_variants_canonical_subsets_and_one_active(
        string $persona,
        array $destinations,
        string $active,
        string $landmark,
    ): void {
        $html = $this->renderNavigation($persona, $destinations, $active);

        self::assertStringContainsString('aria-label="'.$landmark.'"', $html);
        self::assertSame(count($destinations), substr_count($html, 'data-nav-item'));
        self::assertSame(1, substr_count($html, 'aria-current="page"'));
        self::assertStringContainsString('bottom-[calc(0.75rem+env(safe-area-inset-bottom))]', $html);
        self::assertStringContainsString('rounded-full', $html);
        self::assertSame(count($destinations), substr_count($html, 'min-h-touch'));

        $lastPosition = -1;
        foreach ($destinations as $label => $href) {
            $position = strpos($html, '>'.$label.'</span>');
            self::assertNotFalse($position);
            self::assertGreaterThan($lastPosition, $position);
            self::assertStringContainsString('href="'.$href.'"', $html);
            $lastPosition = $position;
        }
    }

    /** @return iterable<string, array{string, array<string, string>, string, string}> */
    public static function validNavigationProvider(): iterable
    {
        yield 'officer full' => ['officer', self::OFFICER_DESTINATIONS, 'Setoran', 'Navigasi petugas'];
        yield 'officer core only' => ['officer', [
            'Tugas' => '/dashboard/petugas',
            'Setoran' => '/petugas/pindai',
            'Akun' => '/profil/kata-sandi',
        ], 'Tugas', 'Navigasi petugas'];
        yield 'officer task only' => ['officer', [
            'Tugas' => '/dashboard/petugas',
        ], 'Tugas', 'Navigasi petugas'];
        yield 'officer one optional' => ['officer', [
            'Tugas' => '/dashboard/petugas',
            'Layanan' => '/petugas/layanan-keliling',
            'Akun' => '/profil/kata-sandi',
        ], 'Layanan', 'Navigasi petugas'];
        yield 'treasurer full' => ['treasurer', self::TREASURER_DESTINATIONS, 'Laporan', 'Navigasi bendahara'];
        yield 'treasurer core only' => ['treasurer', [
            'Tugas' => '/dashboard/bendahara',
            'Pembayaran' => '/bendahara/pencairan',
            'Akun' => '/profil/kata-sandi',
        ], 'Akun', 'Navigasi bendahara'];
    }

    /** @param array<string, string> $destinations */
    #[DataProvider('invalidNavigationProvider')]
    public function test_navigation_rejects_invalid_structure(string $persona, array $destinations, string $active, string $message): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage($message);

        $this->renderNavigation($persona, $destinations, $active);
    }

    /** @return iterable<string, array{string, array<string, string>, string, string}> */
    public static function invalidNavigationProvider(): iterable
    {
        yield 'unknown persona' => ['admin', self::OFFICER_DESTINATIONS, 'Tugas', 'Officer navigation persona must be officer or treasurer.'];
        yield 'missing core' => ['officer', ['Setoran' => '/setoran', 'Akun' => '/akun'], 'Akun', 'Officer navigation must contain Tugas.'];
        yield 'foreign officer item' => ['officer', ['Tugas' => '/tugas', 'Pembayaran' => '/bayar', 'Akun' => '/akun'], 'Tugas', 'Officer navigation destinations are invalid for persona officer.'];
        yield 'foreign treasurer item' => ['treasurer', ['Tugas' => '/tugas', 'Pindai' => '/pindai', 'Akun' => '/akun'], 'Tugas', 'Officer navigation destinations are invalid for persona treasurer.'];
        yield 'reordered' => ['officer', ['Setoran' => '/setoran', 'Tugas' => '/tugas', 'Akun' => '/akun'], 'Tugas', 'Officer navigation destinations must follow canonical order.'];
        yield 'unknown active' => ['officer', self::OFFICER_DESTINATIONS, 'Lainnya', 'Officer navigation active item must be one of the supplied destinations.'];
    }

    /** @param array<string, mixed> $destinations */
    #[DataProvider('invalidDestinationValueProvider')]
    public function test_navigation_rejects_unsafe_destination_values(string $persona, array $destinations, string $message): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage($message);

        $this->renderNavigation($persona, $destinations, 'Tugas');
    }

    /** @return iterable<string, array{string, array<string, mixed>, string}> */
    public static function invalidDestinationValueProvider(): iterable
    {
        $invalidValues = [
            'integer' => 42,
            'array' => ['/petugas'],
            'null' => null,
            'empty' => '',
            'whitespace' => ' /petugas',
            'plain relative' => 'petugas',
            'http external' => 'http://evil.example',
            'https external' => 'https://evil.example',
            'javascript scheme' => 'javascript:alert(1)',
            'data scheme' => 'data:text/html,test',
            'vbscript scheme' => 'vbscript:msgbox(1)',
            'protocol relative' => '//evil.example',
            'backslash' => '/petugas\\akun',
            'UNC' => '\\\\evil.example\\share',
            'control' => "/petugas\nsetoran",
            'encoded scheme' => '%6Aavascript:alert(1)',
            'encoded external' => '%2F%2Fevil.example',
            'encoded tab' => '/petugas%09setoran',
            'encoded newline' => '/petugas%0Asetoran',
            'encoded NUL' => '/petugas%00setoran',
            'encoded DEL' => '/petugas%7Fsetoran',
            'encoded backslash' => '/petugas%5Cakun',
            'double-encoded network separators' => '/petugas%252f%252fevil.example',
            'double-encoded scheme-like payload' => '/%256Aavascript:alert(1)',
            'empty query' => '?',
            'empty fragment' => '#',
            'invalid UTF-8' => '/petugas%FF',
            'incomplete UTF-8' => '/petugas%C3',
            'bare malformed percent' => '/petugas%',
            'malformed percent letters' => '/petugas%ZZ',
            'short percent' => '/petugas%2',
        ];

        foreach (['officer' => self::OFFICER_DESTINATIONS, 'treasurer' => self::TREASURER_DESTINATIONS] as $persona => $baseDestinations) {
            foreach (array_keys($baseDestinations) as $label) {
                foreach ($invalidValues as $case => $value) {
                    $destinations = $baseDestinations;
                    $destinations[$label] = $value;
                    $message = is_string($value)
                        ? "Officer navigation destination for {$label} must be a safe internal path, query, or fragment."
                        : "Officer navigation destination for {$label} must be a string.";

                    yield "{$persona} {$label}: {$case}" => [$persona, $destinations, $message];
                }
            }
        }
    }

    /** @param array<string, string> $destinations */
    #[DataProvider('validDestinationValueProvider')]
    public function test_navigation_preserves_and_escapes_safe_original_destination(
        string $persona,
        array $destinations,
        string $expectedHref,
    ): void {
        $html = $this->renderNavigation($persona, $destinations, 'Tugas');

        self::assertStringContainsString('href="'.$expectedHref.'"', $html);
    }

    /** @return iterable<string, array{string, array<string, string>, string}> */
    public static function validDestinationValueProvider(): iterable
    {
        $validValues = [
            'root path' => ['/petugas', '/petugas'],
            'encoded path' => ['/petugas/ruang%20publik', '/petugas/ruang%20publik'],
            'encoded slash' => ['/petugas%2Ftugas', '/petugas%2Ftugas'],
            'encoded percent literal' => ['/petugas%25', '/petugas%25'],
            'encoded UTF-8' => ['/petugas/tugas-%C3%A9co', '/petugas/tugas-%C3%A9co'],
            'UTF-8 path' => ['/petugas/tugas-éco', '/petugas/tugas-éco'],
            'query and escaping' => ['?area=RW-01&status=baru', '?area=RW-01&amp;status=baru'],
            'UTF-8 fragment' => ['#tugas-日', '#tugas-日'],
        ];

        foreach (['officer' => self::OFFICER_DESTINATIONS, 'treasurer' => self::TREASURER_DESTINATIONS] as $persona => $baseDestinations) {
            foreach (array_keys($baseDestinations) as $label) {
                foreach ($validValues as $case => [$value, $expectedHref]) {
                    $destinations = $baseDestinations;
                    $destinations[$label] = $value;

                    yield "{$persona} {$label}: {$case}" => [$persona, $destinations, $expectedHref];
                }
            }
        }
    }

    public function test_components_remain_presentational_and_routes_are_unchanged(): void
    {
        $paths = [
            resource_path('views/components/layouts/officer.blade.php'),
            resource_path('views/components/officer/header.blade.php'),
            resource_path('views/components/officer/navigation.blade.php'),
        ];
        $source = '';
        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);
            $source .= $contents;
        }

        foreach (['Auth::', 'DB::', 'Model::', 'Livewire\\Component', 'wire:', 'factory(', 'User::', '@can', '@role', 'permission'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }

        self::assertSame(['/', 'login'], collect(app('router')->getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => in_array($route->getName(), ['home', 'login'], true))
            ->map(static fn ($route): string => $route->uri())
            ->values()
            ->all());
    }

    /** @param array<string, mixed> $destinations */
    private function renderNavigation(string $persona, array $destinations, string $active): string
    {
        return Blade::render(
            '<x-officer.navigation :persona="$persona" :destinations="$destinations" :active="$active" />',
            compact('persona', 'destinations', 'active'),
        );
    }
}
