<?php

declare(strict_types=1);

use App\Filament\BackofficeResources;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('redirects an Admin old access bookmark to users without restoring dynamic access tables', function (string $page): void {
    $admin = User::factory()->create(['role' => 'admin']);
    expect(Schema::hasTable('roles'))->toBeFalse()->and(Schema::hasTable('permissions'))->toBeFalse();
    $this->actingAs($admin)->get('/backoffice/identity/models/'.$page)
        ->assertRedirect(UserResource::getUrl('index', panel: 'backoffice'));
    $this->get(UserResource::getUrl('index', panel: 'backoffice'))->assertOk();
})->with(['roles', 'permissions']);

it('does not give other roles access through legacy bookmarks', function (string $role, string $page): void {
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get('/backoffice/identity/models/'.$page)->assertForbidden();
})->with(['warga', 'petugas', 'bendahara'])->with(['roles', 'permissions']);

it('requires authentication for an old access bookmark', function (string $page): void {
    $this->get('/backoffice/identity/models/'.$page)->assertRedirect(route('login'));
})->with(['roles', 'permissions']);

it('lists every current resource explicitly and does not discover retired resources', function (): void {
    $classes = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament/Resources'))) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), 'Resource.php')) {
            $classes[] = 'App\\Filament\\Resources\\'.str_replace(['/', '\\'], '\\', substr($file->getPathname(), strlen(app_path('Filament/Resources')) + 1, -4));
        }
    }
    expect(BackofficeResources::all())->toHaveCount(26)->toEqualCanonicalizing($classes)
        ->and(Filament::getPanel('backoffice')->getResources())->toEqualCanonicalizing(BackofficeResources::all())
        ->and(Filament::getPanel('backoffice')->getResourceDirectories())->toBe([]);
});
