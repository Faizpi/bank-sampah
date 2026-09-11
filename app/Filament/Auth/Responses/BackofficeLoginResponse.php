<?php

declare(strict_types=1);

namespace App\Filament\Auth\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class BackofficeLoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $intended = session()->pull('url.intended');
        $backofficeUrl = Filament::getUrl();

        if (is_string($intended) && str_starts_with($intended, url('/backoffice')) && ! str_contains($intended, '/backoffice/login')) {
            return redirect()->to($intended);
        }

        return redirect()->to($backofficeUrl);
    }
}
