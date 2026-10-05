<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Auth\BackofficeLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class BackofficePanelProvider extends PanelProvider
{
    /**
     * Backoffice uses the same botanical palette as the public and role-based
     * portals, with a deeper primary action tone to keep data-heavy screens calm.
     *
     * @var array<int, string>
     */
    private const PRIMARY_PALETTE = [
        50 => '#F3F7F4',
        100 => '#E2ECE6',
        200 => '#C7D9CF',
        300 => '#A3BEAF',
        400 => '#729B87',
        500 => '#477B67',
        600 => '#185746',
        700 => '#123D32',
        800 => '#0F3028',
        900 => '#0A251E',
        950 => '#061712',
    ];

    /** @var array<int, string> */
    private const WARNING_PALETTE = [
        50 => '#FBF2DC',
        100 => '#F6E7BD',
        200 => '#ECD28B',
        300 => '#DFBD63',
        400 => '#D6A84B',
        500 => '#B88932',
        600 => '#8D6726',
        700 => '#684B20',
        800 => '#49351C',
        900 => '#322617',
        950 => '#1D160D',
    ];

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('backoffice')
            ->path('backoffice')
            ->login(BackofficeLogin::class)
            ->colors([
                'primary' => self::PRIMARY_PALETTE,
                'success' => self::PRIMARY_PALETTE,
                'warning' => self::WARNING_PALETTE,
            ])
            ->darkMode(false)
            ->themeSwitcher(false)
            ->maxContentWidth('max-w-[100rem]')
            ->viteTheme('resources/css/filament/backoffice/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->navigationGroups([
                NavigationGroup::make('Operasional'),
                NavigationGroup::make('Data Master')->collapsed(),
                NavigationGroup::make('Program')->collapsed(),
                NavigationGroup::make('Pengawasan')->collapsed(),
                NavigationGroup::make('Keamanan & Akses')->collapsed(),
                NavigationGroup::make('Administrasi sistem')->collapsed(),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
