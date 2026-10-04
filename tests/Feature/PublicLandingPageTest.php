<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class PublicLandingPageTest extends TestCase
{
    public function test_landing_page_presents_the_service_and_login_access(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Bank Sampah Digital')
            ->assertSee('Sampah tercatat. Nilai terjaga. Desa bergerak bersama.')
            ->assertSee('Akses akun saya')
            ->assertSee('Masuk')
            ->assertSee('href="'.route('login').'"', escape: false)
            ->assertSee('mascot-6.png')
            ->assertDontSee('Contoh ilustratif. Nilai aktual mengikuti jenis, berat, dan harga saat transaksi.')
            ->assertDontSee('Rp18.500');
    }

    public function test_login_route_is_named_and_returns_the_login_page(): void
    {
        $this->get(route('login'))->assertOk();
    }
}
