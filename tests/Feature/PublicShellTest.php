<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PublicShellTest extends TestCase
{
    public function test_home_renders_the_public_shell_landmarks_and_metadata_once(): void
    {
        $response = $this->get(route('home'));
        $html = $response->getContent();

        $response
            ->assertOk()
            ->assertSee('<html lang="id">', escape: false)
            ->assertSee('<title>Bank Sampah Digital Sindangheula</title>', escape: false)
            ->assertSee('content="Layanan bank sampah digital Desa Sindangheula untuk pencatatan setoran, saldo rupiah, penjemputan, dan informasi program desa yang transparan."', escape: false)
            ->assertSee('href="#konten-utama"', escape: false)
            ->assertSee('id="konten-utama"', escape: false)
            ->assertSee('tabindex="-1"', escape: false);

        self::assertSame(1, substr_count($html, '<header'));
        self::assertSame(1, substr_count($html, '<main'));
        self::assertSame(1, substr_count($html, '<footer'));
        self::assertSame(1, substr_count($html, '<h1'));
        self::assertStringContainsString('sticky top-0 z-sticky -mb-[4.75rem] px-3 pt-3', $html);
        self::assertStringNotContainsString('Transparansi untuk setiap setoran', $html);
    }

    public function test_public_desktop_navigation_exposes_grouped_destinations_and_direct_ctas(): void
    {
        $html = $this->get(route('home'))->getContent();
        $expectedHrefs = [
            route('home'),
            route('home').'#layanan',
            route('public.catalog'),
            route('public.prices'),
            route('home').'#cara-kerja',
            route('public.mobile-schedule'),
            route('public.announcements'),
            route('public.programs'),
            route('terms-and-privacy'),
        ];

        self::assertSame(1, preg_match('/<nav\b[^>]*aria-label="Navigasi utama"[^>]*>(?<navigation>.*?)<\/nav>/s', $html, $matches));
        $navigation = $matches['navigation'];
        self::assertSame(2, substr_count($navigation, 'data-public-nav-group'));
        self::assertSame(2, substr_count($navigation, 'data-public-nav-trigger'));
        self::assertStringContainsString('Jelajahi', $navigation);
        self::assertStringContainsString('Informasi publik', $navigation);
        self::assertSame(2, substr_count($navigation, 'aria-expanded="false"'));
        self::assertSame(2, substr_count($navigation, 'data-public-nav-menu'));
        self::assertSame(2, substr_count($navigation, 'hidden data-public-nav-menu'));

        preg_match_all('/<a\b(?=[^>]*\bdata-public-nav-link\b)(?=[^>]*\bhref="(?<href>[^"]+)")[^>]*>/s', $navigation, $links);
        self::assertSame($expectedHrefs, $links['href']);
        self::assertStringContainsString('href="'.route('login').'"', $navigation);
        self::assertStringContainsString('href="'.route('register').'"', $navigation);
        self::assertStringContainsString('xl:flex', $navigation);
        self::assertStringNotContainsString('Kontak', $html);
        self::assertStringNotContainsString('Gardenie', $html);
    }

    public function test_current_desktop_navigation_group_exposes_active_trigger_styling(): void
    {
        $homeHtml = $this->get(route('home'))->assertOk()->getContent();
        $publicInformationHtml = $this->get(route('terms-and-privacy'))->assertOk()->getContent();

        self::assertMatchesRegularExpression(
            '/<button\\b(?=[^>]*\\bdata-public-nav-trigger\\b)(?=[^>]*\\bclass="[^"]*\\bbg-success-bg\\b[^"]*\\bfont-bold\\b[^"]*\\btext-deep-green\\b[^"]*\\bshadow-xs\\b[^"]*")[^>]*>\\s*Jelajahi\\s*</s',
            $homeHtml,
        );
        self::assertMatchesRegularExpression(
            '/<button\\b(?=[^>]*\\bdata-public-nav-trigger\\b)(?=[^>]*\\bclass="[^"]*\\bbg-success-bg\\b[^"]*\\bfont-bold\\b[^"]*\\btext-deep-green\\b[^"]*\\bshadow-xs\\b[^"]*")[^>]*>\\s*Informasi publik\\s*</s',
            $publicInformationHtml,
        );
    }

    public function test_mobile_navigation_trigger_targets_the_shared_bottom_sheet_lifecycle(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('aria-haspopup="dialog"', escape: false)
            ->assertSee('aria-controls="public-mobile-navigation"', escape: false)
            ->assertSee('id="public-mobile-navigation"', escape: false)
            ->assertSee('min-h-touch', escape: false)
            ->assertSee('min-w-touch', escape: false)
            ->assertSee('xl:hidden', escape: false)
            ->assertSee('role="dialog"', escape: false)
            ->assertSee('aria-modal="true"', escape: false)
            ->assertSee('aria-labelledby="public-mobile-navigation-title"', escape: false)
            ->assertSee('aria-describedby="public-mobile-navigation-description"', escape: false)
            ->assertSee('x-on:open-bottom-sheet.window=', escape: false)
            ->assertSee('openModal($event.detail?.invoker)', escape: false)
            ->assertSee('x-trap.inert.noscroll="open"', escape: false);
    }

    public function test_every_mobile_navigation_link_closes_the_shared_sheet_without_blocking_navigation(): void
    {
        $html = $this->get(route('home'))->getContent();

        self::assertMatchesRegularExpression('/<nav aria-label="Navigasi seluler"[^>]*>(?<navigation>.*?)<\/nav>/s', $html);
        preg_match('/<nav aria-label="Navigasi seluler"[^>]*>(?<navigation>.*?)<\/nav>/s', $html, $matches);
        $navigation = $matches['navigation'];

        preg_match_all('/<a\s+[^>]*href="(?<href>[^"]+)"[^>]*>/s', $navigation, $links);

        self::assertSame([
            route('home'),
            route('home').'#layanan',
            route('public.catalog'),
            route('public.prices'),
            route('home').'#cara-kerja',
            route('public.mobile-schedule'),
            route('public.announcements'),
            route('public.programs'),
            route('terms-and-privacy'),
            route('login'),
            route('register'),
        ], $links['href']);
        preg_match_all('/<a\s+[^>]*href="(?<href>[^"]+)"[^>]*>(?<label>.*?)<\/a>/s', $navigation, $labeledLinks);
        $labels = array_map(
            static fn (string $label): string => trim((string) preg_replace('/\s+/', ' ', strip_tags($label))),
            $labeledLinks['label'],
        );
        self::assertSame([
            'Beranda',
            'Layanan',
            'Katalog',
            'Harga',
            'Cara kerja',
            'Jadwal keliling',
            'Pengumuman',
            'Target dan statistik',
            'Ketentuan dan privasi',
            'Masuk',
            'Daftar',
        ], $labels);
        self::assertCount(11, $links[0]);

        foreach ($links[0] as $link) {
            self::assertStringContainsString('x-on:click="closeModal()"', $link);
            self::assertStringNotContainsString('prevent', $link);
        }

        self::assertSame(11, substr_count($navigation, 'x-on:click="closeModal()"'));
        self::assertStringNotContainsString('x-data=', $navigation);
        self::assertStringNotContainsString('openModal(', $navigation);
    }

    public function test_public_shell_preserves_current_landing_sections_and_account_destinations(): void
    {
        $response = $this->get(route('home'));
        $html = $response->getContent();

        $response
            ->assertSee('id="layanan"', escape: false)
            ->assertSee('id="cara-kerja"', escape: false)
            ->assertSee('Sampah tercatat.')
            ->assertSee('Nilai terjaga.')
            ->assertSee('Desa bergerak bersama.')
            ->assertSee('Akses Akun Saya')
            ->assertSee('Masuk Ke Sistem')
            ->assertSee('mascot-6.png');

        $response
            ->assertDontSee('Gerak bersama')
            ->assertDontSee('Setiap pilahan punya nilai')
            ->assertDontSee('Contoh nilai tercatat')
            ->assertDontSee('Rp18.500')
            ->assertDontSee('3,000 kg × Rp3.000')
            ->assertDontSee('Contoh ilustratif. Nilai aktual mengikuti jenis, berat, dan harga saat transaksi.');

        self::assertGreaterThanOrEqual(3, substr_count($html, 'href="'.route('login').'"'));
        self::assertGreaterThanOrEqual(3, substr_count($html, 'href="'.route('register').'"'));
        self::assertStringContainsString('public-container', $html);
        self::assertStringContainsString('xl:flex', $html);
        self::assertStringContainsString('xl:hidden', $html);
    }
}
