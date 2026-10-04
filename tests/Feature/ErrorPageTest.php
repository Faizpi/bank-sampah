<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ErrorPageTest extends TestCase
{
    public function test_missing_page_uses_branded_recovery_screen(): void
    {
        config()->set('app.debug', false);

        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Kembali ke beranda')
            ->assertSee('images/landing/mascot-3.png')
            ->assertSee(route('home'), false);
    }

    public function test_http_errors_have_clear_recovery_copy(): void
    {
        config()->set('app.debug', false);

        $cases = [
            403 => 'Akses tidak diizinkan',
            419 => 'Sesi sudah berakhir',
            429 => 'Terlalu banyak permintaan',
            500 => 'Terjadi gangguan sementara',
            503 => 'Layanan sedang dalam perawatan',
        ];

        foreach ($cases as $status => $expectedCopy) {
            Route::get("/__test/http-error-{$status}", static function () use ($status): void {
                abort($status);
            });

            $this->get("/__test/http-error-{$status}")
                ->assertStatus($status)
                ->assertSee($expectedCopy)
                ->assertSee('Kembali ke beranda');
        }
    }

    public function test_unknown_client_error_uses_group_fallback(): void
    {
        config()->set('app.debug', false);

        Route::get('/__test/http-error-418', static function (): void {
            abort(418);
        });

        $this->get('/__test/http-error-418')
            ->assertStatus(418)
            ->assertSee('Permintaan tidak dapat diproses')
            ->assertSee('Kembali ke beranda');
    }
}
