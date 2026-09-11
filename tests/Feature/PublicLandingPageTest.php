<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicLandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_presents_the_service_and_login_access(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Akses Akun Saya')
            ->assertSee('Masuk')
            ->assertSee('href="'.route('login').'"', escape: false)
            ->assertDontSee('Contoh ilustratif. Nilai aktual mengikuti jenis, berat, dan harga saat transaksi.')
            ->assertDontSee('Rp18.500');
    }

    public function test_login_route_is_named_and_returns_the_login_page(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_approved_public_pages_have_unique_metadata_and_absolute_self_canonicals(): void
    {
        config()->set('app.url', 'https://bank-sampah.test');

        $pages = [
            'home' => ['Bank Sampah Digital', 'Layanan bank sampah digital untuk pencatatan setoran, saldo rupiah, penjemputan, dan informasi program yang transparan.'],
            'terms-and-privacy' => ['Ketentuan Operasional dan Kebijakan Privasi | Bank Sampah Digital', 'Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 Bank Sampah Digital.'],
            'public.catalog' => ['Katalog Sampah dan Edukasi', 'Pelajari jenis sampah yang diterima, satuan, kondisi, dan panduan pemilahannya di Bank Sampah Digital.'],
            'public.prices' => ['Harga Sampah Aktif', 'Lihat harga sampah aktif per kondisi dan satuan di Bank Sampah Digital.'],
            'public.announcements' => ['Pengumuman', 'Pengumuman resmi Bank Sampah Digital.'],
            'public.mobile-schedule' => ['Jadwal Bank Sampah Keliling', 'Jadwal layanan keliling Bank Sampah Digital.'],
            'public.programs' => ['Target dan Statistik Program', 'Target pengumpulan dan statistik ringkasan publik Bank Sampah Digital.'],
            'public.tutorials' => ['Tutorial Penggunaan dan Panduan Video | Bank Sampah Digital', 'Kumpulan panduan video tutorial resmi Google Drive untuk warga, petugas, bendahara, dan administrator Bank Sampah Digital.'],
        ];

        foreach ($pages as $routeName => [$title, $description]) {
            $response = $this->get(route($routeName))->assertOk();
            $response
                ->assertSee("<title>{$title}</title>", escape: false)
                ->assertSee('name="description" content="'.$description.'"', escape: false)
                ->assertSee('rel="canonical" href="'.route($routeName).'"', escape: false)
                ->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_google_verification_meta_is_only_rendered_for_a_non_empty_configured_token(): void
    {
        config()->set('app.google_site_verification', null);
        $this->get(route('home'))->assertOk()->assertDontSee('name="google-site-verification"', escape: false);

        config()->set('app.google_site_verification', '  search-console-token  ');
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('name="google-site-verification" content="search-console-token"', escape: false);
    }

    public function test_sitemap_contains_exactly_the_approved_public_route_allowlist(): void
    {
        config()->set('app.url', 'https://bank-sampah.test');
        $approvedRoutes = [
            'home',
            'terms-and-privacy',
            'public.catalog',
            'public.prices',
            'public.announcements',
            'public.mobile-schedule',
            'public.programs',
            'public.tutorials',
        ];

        $response = $this->get(route('sitemap'))->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = $response->getContent();

        self::assertSame(8, substr_count($xml, '<url>'));
        self::assertSame(8, substr_count($xml, '<loc>'));
        foreach ($approvedRoutes as $routeName) {
            self::assertStringContainsString('<loc>'.route($routeName).'</loc>', $xml);
        }
        foreach (['login', 'register', 'health', 'public.deposit-verification'] as $routeName) {
            self::assertStringNotContainsString(parse_url(route($routeName, $routeName === 'public.deposit-verification' ? ['token' => str_repeat('a', 43)] : []), PHP_URL_PATH), $xml);
        }
        self::assertStringNotContainsString('/backoffice', $xml);
    }

    public function test_tutorials_page_renders_all_configured_roles_and_google_drive_links(): void
    {
        $response = $this->get(route('public.tutorials'));
        $response->assertOk();
        $response->assertSee('Tutorial Penggunaan Sistem Bank Sampah');
        $response->assertSee('Sesi Warga');
        $response->assertSee('Sesi Petugas');
        $response->assertSee('Sesi Bendahara');
        $response->assertSee('Sesi Superadmin / Admin');
        $response->assertSee('Tonton Video (Google Drive)');
        $response->assertSee('1. Tutorial Masuk Aplikasi');
    }

    public function test_robots_references_absolute_sitemap_and_excludes_non_search_surfaces(): void
    {
        config()->set('app.url', 'https://bank-sampah.test');

        $response = $this->get(route('robots'))->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $robots = $response->getContent();

        self::assertStringContainsString('Sitemap: '.route('sitemap'), $robots);
        foreach (['/login', '/daftar', '/health', '/operations/', '/verifikasi/', '/dashboard/', '/warga/', '/petugas/', '/bendahara/', '/statistik/internal', '/profil/', '/notifikasi', '/media/', '/laporan/ekspor/', '/backoffice'] as $path) {
            self::assertStringContainsString('Disallow: '.$path, $robots);
        }
        self::assertStringNotContainsString('/verifikasi/setoran/', $robots);
    }

    public function test_non_public_utility_auth_and_token_responses_are_noindex(): void
    {
        $this->get(route('login'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('register'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('health'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get(route('public.deposit-verification', ['token' => str_repeat('a', 43)]))
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
