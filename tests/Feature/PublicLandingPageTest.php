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

    public function test_admin_access_is_a_full_width_white_button_with_a_green_border(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[@href="'.route('filament.backoffice.auth.login').'"]');
        self::assertSame(1, $links->length);
        $link = $links->item(0);
        self::assertStringContainsString('Akses Panel Admin', $link->textContent);
        foreach (['w-full', 'min-h-touch', 'border-2', 'border-forest-600', 'bg-surface', 'text-forest-700', 'focus-visible:ring-2'] as $class) {
            self::assertContains($class, explode(' ', $link->getAttribute('class')));
        }
        self::assertNotContains('underline', explode(' ', $link->getAttribute('class')));
    }

    public function test_all_seven_hero_photos_refer_to_existing_readable_jpegs(): void
    {
        $response = $this->get(route('home'))->assertOk();
        foreach (range(1, 7) as $number) {
            $path = 'images/landing/'.$number.'.jpg';
            $response->assertSee('src="'.asset($path).'"', false);
            self::assertFileIsReadable(public_path($path));
            self::assertSame('image/jpeg', getimagesize(public_path($path))['mime']);
        }
    }

    public function test_public_copy_has_no_internal_notes_or_repeated_price_explanations(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('Ringkasan bank sampah')
            ->assertSee('Sampah yang diterima')
            ->assertSee('Warga aktif')->assertSee('Petugas aktif')
            ->assertDontSee('Petugas aktif memiliki penugasan berlaku')
            ->assertDontSee('Hanya setoran final yang belum dibalik dihitung')
            ->assertDontSee('berarti belum dipublikasikan atau dilindungi')
            ->assertDontSee('Foto data lama dapat dilengkapi Admin')
            ->assertDontSee('bersumber dari katalog yang dikelola Admin')
            ->assertDontSee('Nilai akhir mengikuti berat aktual dan harga saat setoran')
            ->assertDontSee('Informasi publik yang jelas untuk keputusan yang lebih baik');

        $this->get(route('public.catalog'))->assertOk()
            ->assertSee('Jenis sampah yang diterima')
            ->assertDontSee('Yang dapat dipelajari dan disiapkan')
            ->assertSee('Lihat harga aktif');

        $this->get(route('public.prices'))->assertOk()
            ->assertSee('Harga sampah saat ini')
            ->assertDontSee('Harga publik adalah informasi aktif')
            ->assertDontSee('Harga yang tercatat pada transaksi final disimpan')
            ->assertDontSee('Gunakan informasi ini sebagai acuan sebelum setoran');
    }

    public function test_village_head_section_immediately_follows_the_hero(): void
    {
        $this->get(route('home'))->assertOk()->assertSeeInOrder([
            'aria-labelledby="landing-title"',
            'aria-labelledby="village-head-title"',
            'id="ringkasan"',
            'id="sampah-diterima"',
            'id="layanan"',
        ], false);
    }

    public function test_approved_public_pages_have_unique_metadata_and_absolute_self_canonicals(): void
    {
        config()->set('app.url', 'https://bank-sampah.test');

        $pages = [
            'home' => ['Bank Sampah Digital Sindangheula', 'Layanan bank sampah digital Desa Sindangheula untuk pencatatan setoran, saldo rupiah, penjemputan, dan informasi program desa yang transparan.'],
            'terms-and-privacy' => ['Ketentuan Operasional dan Kebijakan Privasi | Bank Sampah Digital Sindangheula', 'Ketentuan Operasional v1.0 dan Kebijakan Privasi Ringkas v1.0 Bank Sampah Digital Sindangheula.'],
            'public.catalog' => ['Katalog Sampah dan Edukasi', 'Pelajari jenis sampah yang diterima, satuan, kondisi, dan panduan pemilahannya di Bank Sampah Digital Sindangheula.'],
            'public.prices' => ['Harga Sampah Aktif', 'Lihat harga sampah aktif per kondisi dan satuan di Bank Sampah Digital Sindangheula.'],
            'public.announcements' => ['Pengumuman', 'Pengumuman resmi Bank Sampah Digital Desa Sindangheula.'],
            'public.mobile-schedule' => ['Jadwal Bank Sampah Keliling', 'Jadwal layanan keliling Bank Sampah Digital Desa Sindangheula.'],
            'public.programs' => ['Target dan Statistik Program', 'Target pengumpulan dan statistik ringkasan publik Bank Sampah Digital Desa Sindangheula.'],
            'public.tutorials' => ['Tutorial Penggunaan dan Panduan Video | Bank Sampah Digital Sindangheula', 'Kumpulan panduan panduan penggunaan untuk warga, petugas, bendahara, dan administrator Bank Sampah Digital Desa Sindangheula.'],
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
        $response->assertSee('Sesi Admin');
        $response->assertSee('Video belum tersedia.');
        $response->assertSee('Akun dan saldo');
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
