<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\StatusLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Regression locks for the remaining validated UI audit items
 * (anti-slop/audit-001-2026-09-12.md): F-14 radius pill, F-26 raw status
 * labels, F-30 unused magic-wand icon, F-31 duplicate Filament login styles.
 *
 * These are static/rendered assertions only; they do not replace runtime,
 * responsive, contrast, or browser-based accessibility evidence.
 */
final class UiAuditFixesTest extends TestCase
{
    use RefreshDatabase;

    private function compiledThemeCss(): string
    {
        $manifest = public_path('build/manifest.json');
        self::assertFileExists($manifest, 'Vite manifest missing; run `npm run build`.');

        $decoded = json_decode((string) File::get($manifest), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('resources/css/filament/backoffice/theme.css', $decoded);

        $cssPath = public_path('build/'.$decoded['resources/css/filament/backoffice/theme.css']['file']);
        self::assertFileExists($cssPath);

        return (string) File::get($cssPath);
    }

    public function test_f14_shared_public_navigation_uses_control_and_container_radius_tokens(): void
    {
        $header = (string) File::get(resource_path('views/components/public/header.blade.php'));
        self::assertStringNotContainsString('rounded-full', $header, 'F-14: header tidak boleh memakai pill.');
        self::assertStringContainsString('rounded-lg border border-border/90', $header, 'F-14: kontainer header pakai radius-lg.');
        self::assertSame(18, substr_count($header, 'rounded-md'), 'F-14: kontrol header (trigger, link, CTA, tombol menu) pakai radius-md.');

        $bottomNav = (string) File::get(resource_path('views/components/ui/bottom-navigation.blade.php'));
        self::assertStringContainsString('max-w-citizen -translate-x-1/2 rounded-lg', $bottomNav, 'F-14: kontainer bottom-nav pakai radius-lg.');
        self::assertStringContainsString('gap-1 rounded-md px-1', $bottomNav, 'F-14: item bottom-nav pakai radius-md.');
        self::assertStringNotContainsString('rounded-full', $bottomNav, 'F-14: bottom-nav tidak boleh memakai pill.');
    }

    public function test_f14_legitimate_status_pills_are_preserved(): void
    {
        // Status badge contract (DESIGN.md: tinggi 28, padding 6x10, radius-sm).
        $statusBadge = (string) File::get(resource_path('views/components/ui/status-badge.blade.php'));
        self::assertStringContainsString('rounded-sm', $statusBadge);
        self::assertStringNotContainsString('rounded-full', $statusBadge);

        // Bottom-nav notification badge tetap pill sah (radius-full untuk dot).
        $bottomNav = (string) File::get(resource_path('views/components/ui/bottom-navigation.blade.php'));
        self::assertStringContainsString('min-w-5 rounded-sm bg-terracotta', $bottomNav);

        // Avatar/dot dekoratif tetap bulat; hanya kontrol yang diturunkan radiusnya.
        $tutorials = (string) File::get(resource_path('views/public/tutorials.blade.php'));
        self::assertStringContainsString('size-1 rounded-full', $tutorials);
        self::assertStringContainsString('size-1.5 rounded-full', $tutorials);
        self::assertStringContainsString('size-12 items-center justify-center rounded-full', $tutorials);
    }

    public function test_f26_officer_dashboard_localizes_every_status_through_status_label(): void
    {
        $dashboard = (string) File::get(resource_path('views/livewire/officer/dashboard.blade.php'));
        self::assertStringNotContainsString('ucwords(str_replace', $dashboard, 'F-26: tidak boleh lagi menyusun label mentah.');
        self::assertStringContainsString('\App\Support\StatusLabel::for($priorityTask[\'status\'])', $dashboard);
        self::assertStringContainsString('\App\Support\StatusLabel::for($pickup->status)', $dashboard);
        self::assertStringContainsString('\App\Support\StatusLabel::for($redemption->status)', $dashboard);

        // Helper tetap memberi label lokal, bukan istilah mentah.
        self::assertSame('Menunggu pemeriksaan', StatusLabel::for('menunggu_pemeriksaan'));
        self::assertSame('Dibatalkan', StatusLabel::for('dibatalkan'));

        // Nilai enum mentah sesuai enum domain: view harus memakai helper, bukan fallback Str::headline.
        self::assertStringContainsString('StatusLabel', $dashboard);
        self::assertStringNotContainsString('Menunggu_pemeriksaan', $dashboard);
        self::assertStringNotContainsString("'menunggu_pemeriksaan'", $dashboard);

        // Detail warga memakai helper yang sama, bukan label mentah.
        foreach ([
            'pickup-show.blade.php' => '$pickup->status',
            'withdrawal-show.blade.php' => '$withdrawal->status',
            'grocery-show.blade.php' => '$redemption->status',
        ] as $file => $expression) {
            $view = (string) File::get(resource_path('views/livewire/citizen/'.$file));
            self::assertStringNotContainsString('ucwords(str_replace', $view, 'F-26: '.$file.' masih menyusun label mentah.');
            self::assertStringContainsString('\App\Support\StatusLabel::for('.$expression.')', $view, 'F-26: '.$file.' memakai StatusLabel.');
        }
    }

    public function test_f30_magic_wand_icon_is_removed_from_registry_and_switch(): void
    {
        $icon = (string) File::get(resource_path('views/components/public/icon.blade.php'));
        self::assertStringNotContainsString('magic-wand', $icon);
        self::assertStringNotContainsString("@case('magic-wand')", $icon);

        // Ikon lain tetap utuh.
        self::assertStringContainsString("'megaphone'", $icon);
        self::assertStringContainsString("@case('megaphone')", $icon);
        self::assertStringContainsString("@case('search')", $icon);

        // Tidak ada konsumen yang memakai ikon ini.
        $matches = File::allFiles(resource_path('views'));
        foreach ($matches as $file) {
            self::assertStringNotContainsString('name="magic-wand"', (string) File::get($file->getPathname()), $file->getPathname());
        }
    }

    public function test_f31_login_layout_has_a_single_source_of_truth_with_compiled_evidence(): void
    {
        $loginView = (string) File::get(resource_path('views/filament/backoffice/auth/login.blade.php'));
        self::assertStringNotContainsString('<style>', $loginView, 'F-31: tidak boleh ada <style> inline pada login.');
        self::assertStringNotContainsString('fi-simple-page', $loginView, 'F-31: aturan layout tidak boleh ditulis di view.');
        self::assertStringContainsString('class="backoffice-auth-login', $loginView, 'F-31: view hanya menandai halaman.');

        $theme = (string) File::get(resource_path('css/filament/backoffice/theme.css'));
        self::assertStringContainsString('max-width: 68rem !important', $theme, 'F-31: layout login 68rem ada di theme.css.');
        self::assertStringNotContainsString('58rem', $theme, 'F-31: definisi 58rem yang bertabrakan sudah tidak ada.');
        self::assertStringContainsString('.backoffice-auth-login', $theme);
        self::assertStringContainsString('.fi-simple-layout:has(.backoffice-auth-login)', $theme);

        // Bukti CSS benar-benar terkompilasi dengan layout yang dituju.
        $compiled = $this->compiledThemeCss();
        self::assertStringContainsString('68rem', $compiled, 'F-31: 68rem harus muncul di CSS terkompilasi.');
        self::assertStringContainsString('backoffice-auth-login', $compiled, 'F-31: marker login harus ada di CSS terkompilasi.');
    }
}
