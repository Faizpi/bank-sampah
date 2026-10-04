<?php

declare(strict_types=1);

namespace Tests\Feature\Wave10;

use Tests\TestCase;

final class PwaContractTest extends TestCase
{
    public function test_service_worker_caches_only_versioned_assets_and_public_allowlist(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        self::assertIsString($serviceWorker);
        self::assertStringContainsString("const CACHE_VERSION = 'v2';", $serviceWorker);
        self::assertStringContainsString("'/katalog-sampah'", $serviceWorker);
        self::assertStringContainsString("'/harga-sampah'", $serviceWorker);
        self::assertStringContainsString("'/icons/icon-192x192.png'", $serviceWorker);
        self::assertStringContainsString("'/icons/icon-512x512.png'", $serviceWorker);
        self::assertStringContainsString("path.startsWith('/build/')", $serviceWorker);
        self::assertStringContainsString("request.method !== 'GET'", $serviceWorker);
        self::assertStringContainsString("request.mode === 'navigate'", $serviceWorker);
        self::assertStringContainsString('PUBLIC_NAVIGATION_ALLOWLIST.has(url.pathname)', $serviceWorker);
        self::assertStringContainsString('PUBLIC_STATIC_ASSET_ALLOWLIST.has(path)', $serviceWorker);
        self::assertStringContainsString('precachePublicStaticAssets()', $serviceWorker);
        self::assertStringContainsString('cacheFirstPublicAsset(request)', $serviceWorker);
    }

    public function test_manifest_references_valid_static_icon_assets(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([
            [
                'src' => '/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src' => '/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ],
        ], $manifest['icons']);

        foreach ($manifest['icons'] as $icon) {
            $dimensions = getimagesize(public_path(ltrim($icon['src'], '/')));

            self::assertIsArray($dimensions);
            self::assertSame($icon['sizes'], $dimensions[0].'x'.$dimensions[1]);
        }
    }

    public function test_service_worker_keeps_private_and_financial_requests_network_only(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));

        self::assertIsString($serviceWorker);
        self::assertStringContainsString('return fetch(request);', $serviceWorker);
        self::assertStringNotContainsString("'/dashboard/warga'", $serviceWorker);
        self::assertStringNotContainsString("'/warga/riwayat-setoran'", $serviceWorker);
        self::assertStringNotContainsString("'/bendahara/pencairan'", $serviceWorker);
        self::assertStringNotContainsString('CacheStorage', $serviceWorker);
    }

    public function test_installable_shell_registers_the_worker_and_blocks_offline_state_changes(): void
    {
        $manifest = file_get_contents(public_path('manifest.webmanifest'));
        $script = file_get_contents(resource_path('js/app.js'));
        $publicLayout = file_get_contents(resource_path('views/layouts/public.blade.php'));
        $citizenLayout = file_get_contents(resource_path('views/components/layouts/citizen.blade.php'));
        $officerLayout = file_get_contents(resource_path('views/components/layouts/officer.blade.php'));

        self::assertIsString($manifest);
        self::assertIsString($script);
        self::assertIsString($publicLayout);
        self::assertIsString($citizenLayout);
        self::assertIsString($officerLayout);
        self::assertStringContainsString('"display": "standalone"', $manifest);
        self::assertStringContainsString("navigator.serviceWorker.register('/sw.js')", $script);
        self::assertStringNotContainsString('localStorage', $script);
        self::assertStringNotContainsString('indexedDB', $script);
        self::assertStringNotContainsString('queue', $script);
        self::assertStringContainsString('Livewire.interceptRequest', $script);
        self::assertStringContainsString('request.cancel()', $script);
        self::assertStringContainsString("document.addEventListener('livewire:init'", $script);
        self::assertStringContainsString('Koneksi diperlukan untuk melanjutkan tindakan ini.', $script);
        self::assertStringContainsString("document.addEventListener('submit'", $script);
        self::assertStringContainsString('HTMLFormElement', $script);
        self::assertStringContainsString('blockNativeOfflineAction', $script);
        self::assertStringContainsString('event.preventDefault()', $script);
        self::assertStringContainsString('stopImmediatePropagation()', $script);
        // Only the two documented navigation handles bind to 'click' (public nav + bottom-sheet trigger);
        // the offline/Livewire guard must never attach a click listener of its own.
        self::assertSame(2, substr_count($script, "document.addEventListener('click'"));
        self::assertStringContainsString('[data-public-navigation-trigger]', $script);
        self::assertStringNotContainsString("document.addEventListener('input'", $script);
        self::assertStringNotContainsString("document.addEventListener('change'", $script);
        self::assertStringNotContainsString("document.addEventListener('blur'", $script);
        self::assertStringNotContainsString('LIVEWIRE_LOCAL_ACTIONS', $script);
        self::assertStringContainsString("const PUBLIC_CACHE_PREFIX = 'bank-sampah-public-'", $script);
        self::assertStringContainsString("document.body.dataset.pwaLogoutConfirmed === 'true'", $script);
        self::assertStringContainsString('clearPublicCachesAfterLogout()', $script);
        self::assertStringContainsString('pwa_logout_confirmed', $publicLayout);
        self::assertStringContainsString('rel="icon"', $publicLayout);
        self::assertStringContainsString('rel="icon"', $citizenLayout);
        self::assertStringContainsString('rel="icon"', $officerLayout);
        self::assertStringContainsString('rel="manifest"', $publicLayout);
        self::assertStringContainsString('rel="manifest"', $citizenLayout);
        self::assertStringContainsString('rel="manifest"', $officerLayout);
        self::assertStringContainsString('<x-public.offline-status />', $publicLayout);
        self::assertStringContainsString('<x-public.offline-status />', $citizenLayout);
        self::assertStringContainsString('<x-public.offline-status />', $officerLayout);
    }

    public function test_livewire_offline_guard_covers_server_bound_directives_without_touching_local_events_or_navigation(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $customerIdentification = file_get_contents(resource_path('views/livewire/officer/customer-identification.blade.php'));
        $registerForm = file_get_contents(resource_path('views/livewire/auth/register-citizen-form.blade.php'));
        $livewireRuntime = file_get_contents(base_path('vendor/livewire/livewire/dist/livewire.js'));
        $directiveFixture = '<input wire:model="name" wire:model.live="name" wire:change="changed" wire:input="typed" wire:blur="left"><button wire:click="confirm"></button><a wire:navigate href="/harga-sampah">Harga</a>';

        self::assertIsString($script);
        self::assertIsString($customerIdentification);
        self::assertIsString($registerForm);
        self::assertIsString($livewireRuntime);
        self::assertStringContainsString('wire:model', $customerIdentification);
        self::assertStringContainsString('wire:click="confirm"', $customerIdentification);
        self::assertStringContainsString('wire:model.blur', $registerForm);
        self::assertStringContainsString('wire:model', $directiveFixture);
        self::assertStringContainsString('wire:model.live', $directiveFixture);
        self::assertStringContainsString('wire:change', $directiveFixture);
        self::assertStringContainsString('wire:input', $directiveFixture);
        self::assertStringContainsString('wire:blur', $directiveFixture);
        self::assertStringContainsString('wire:click', $directiveFixture);
        self::assertStringContainsString('wire:navigate', $directiveFixture);
        self::assertStringContainsString('model.live', $livewireRuntime);
        self::assertStringContainsString('interceptRequest(callback)', $livewireRuntime);
        self::assertStringContainsString('Livewire.interceptRequest', $script);
        self::assertStringContainsString('request.cancel()', $script);
        self::assertStringContainsString("document.addEventListener('submit'", $script);
        // Only the two documented navigation click handlers are allowed; the offline guard
        // must not bind global listeners to Livewire local events (input/change/blur).
        self::assertSame(2, substr_count($script, "document.addEventListener('click'"));
        self::assertStringContainsString('[data-public-navigation-trigger]', $script);
        self::assertStringContainsString('[data-public-nav-group]', $script);
        self::assertStringNotContainsString("document.addEventListener('input'", $script);
        self::assertStringNotContainsString("document.addEventListener('change'", $script);
        self::assertStringNotContainsString("document.addEventListener('blur'", $script);
        self::assertStringNotContainsString('LIVEWIRE_LOCAL_ACTIONS', $script);
        self::assertStringNotContainsString('replay', $script);
        self::assertStringNotContainsString('localStorage', $script);
        self::assertStringNotContainsString('sessionStorage', $script);
        self::assertStringNotContainsString('indexedDB', $script);
    }
}
