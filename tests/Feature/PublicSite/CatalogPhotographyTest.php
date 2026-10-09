<?php

declare(strict_types=1);

use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\WasteMaster\Actions\SaveWasteCatalogType;
use App\Domain\WasteMaster\Actions\SaveWasteTypePhoto;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteTypePhoto;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages\ManageWasteTypes;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Storage::fake('waste_photos');
    Storage::fake('media_private');
    $this->admin = User::where('role', 'admin')->firstOrFail();
    $this->officer = User::where('role', 'petugas')->firstOrFail();
    $this->type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->data = ['code' => 'BOTOL-BARU', 'name' => 'Botol Baru', 'waste_category_id' => $this->type->waste_category_id,
        'waste_unit_id' => $this->type->waste_unit_id, 'condition_ids' => $this->type->conditions->modelKeys(),
        'education_description' => 'Kosongkan dan keringkan.', 'sort_order' => 5, 'is_active' => true, 'is_plastic' => true];
});

it('uses the same photo name and current price on all three public surfaces', function (): void {
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->image('botol.png', 400, 300));
    $url = route('public.waste-photo', [$this->type, $photo->uuid]);
    foreach (['home', 'public.catalog', 'public.prices'] as $route) {
        $this->get(route($route))->assertOk()->assertSee('Aqua Botol')->assertSee('Rp1.500')->assertSee($url, false)
            ->assertDontSee('/storage/')->assertDontSee('media_private')->assertDontSee('uploaded_by');
    }
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    expect($photo->size)->toBeLessThanOrEqual(1024 * 1024);
});

it('replaces a photo once and invalidates the previous URL without changing prices or transactions', function (): void {
    $action = app(SaveWasteTypePhoto::class);
    $old = $action->handle($this->admin, $this->type, UploadedFile::fake()->image('before.jpg'));
    $oldUrl = route('public.waste-photo', [$this->type, $old->uuid]);
    $new = $action->handle($this->admin, $this->type, UploadedFile::fake()->image('after.png'));
    $newUrl = route('public.waste-photo', [$this->type, $new->uuid]);
    $this->get($oldUrl)->assertNotFound();
    $this->get($newUrl)->assertOk();
    foreach (['home', 'public.catalog', 'public.prices'] as $route) {
        $this->get(route($route))->assertSee($newUrl, false)->assertDontSee($oldUrl, false);
    }
    expect(WasteTypePhoto::count())->toBe(1)->and($this->type->prices()->first()->price)->toBe(1500);
    $this->assertDatabaseCount('deposits', 0);
});

it('keeps private evidence private even when a legacy media reference exists on a waste type', function (): void {
    $media = app(StorePrivateMedia::class)->handlePhoto(UploadedFile::fake()->image('private.jpg'), $this->admin);
    WasteMasterMutationGuard::run(fn () => $this->type->forceFill(['media_id' => $media->id])->save());
    $this->get(route('public.waste-photo', [$this->type, $media->uuid]))->assertNotFound();
    foreach (['home', 'public.catalog', 'public.prices'] as $route) {
        $this->get(route($route))->assertOk()->assertDontSee($media->uuid)->assertDontSee($media->path);
    }
});

it('rejects photos belonging to other types and hides inactive types from guests', function (): void {
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->image('type.jpg'));
    $other = WasteType::where('code', 'KARDUS')->firstOrFail();
    $this->get(route('public.waste-photo', [$other, $photo->uuid]))->assertNotFound();
    WasteMasterMutationGuard::run(fn () => $this->type->forceFill(['is_active' => false])->save());
    $url = route('public.waste-photo', [$this->type, $photo->uuid]);
    $this->get($url)->assertNotFound();
    $this->actingAs($this->admin)->get($url)->assertOk();
});

it('does not publish a missing or path-tampered photo', function (): void {
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->image('type.jpg'));
    Storage::disk('waste_photos')->delete($photo->path);
    expect(app(PublicWasteCatalog::class)->photoUrl($this->type->fresh()))->toBeNull();
    $this->get(route('public.waste-photo', [$this->type, $photo->uuid]))->assertNotFound();
    $photo->forceFill(['path' => '../secret.jpg'])->save();
    $this->get(route('public.waste-photo', [$this->type, $photo->uuid]))->assertNotFound();
});

it('does not let a non-admin upload catalogue photography', function (): void {
    expect(fn () => app(SaveWasteTypePhoto::class)->handle($this->officer, $this->type, UploadedFile::fake()->image('type.jpg')))->toThrow(AuthorizationException::class);
    expect(WasteTypePhoto::count())->toBe(0)->and(Storage::disk('waste_photos')->allFiles())->toBe([]);
});

it('requires a real photo for new catalogue entries and rolls back malformed edits', function (): void {
    $action = app(SaveWasteCatalogType::class);
    expect(fn () => $action->handle($this->admin, null, $this->data))->toThrow(ValidationException::class);
    $name = $this->type->name;
    expect(fn () => $action->handle($this->admin, $this->type, [...$this->data, 'photo' => UploadedFile::fake()->createWithContent('bad.jpg', '<?php echo "not an image";')]))->toThrow(ValidationException::class);
    expect($this->type->fresh()->name)->toBe($name)->and(WasteType::count())->toBe(4);
});

it('strips uploaded metadata instead of exposing it in the public photo', function (): void {
    $jpeg = UploadedFile::fake()->image('photo.jpg')->getContent();
    $metadata = 'PRIVATE-GPS-LOCATION';
    $withMetadata = substr($jpeg, 0, 2)."\xFF\xFE".pack('n', strlen($metadata) + 2).$metadata.substr($jpeg, 2);
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->createWithContent('photo.jpg', $withMetadata));
    expect(Storage::disk('waste_photos')->get($photo->path))->not->toContain($metadata);
});

it('requires the Admin creation form photo and preserves stored photography on a text-only edit', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('backoffice'));
    $this->actingAs($this->admin);
    Livewire::test(ManageWasteTypes::class)->callAction('create', data: $this->data)->assertHasActionErrors(['photo' => 'required']);
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->image('keep.jpg'));
    Livewire::test(ManageWasteTypes::class)->callTableAction('edit', $this->type, data: [...$this->data, 'code' => $this->type->code, 'photo' => null])->assertHasNoErrors();
    expect($this->type->fresh()->publicPhoto->uuid)->toBe($photo->uuid);
});

it('removes unaccepted price condition pairs and inactive units from every public surface', function (): void {
    WasteMasterMutationGuard::run(fn () => $this->type->conditions()->detach());
    foreach (['home', 'public.catalog', 'public.prices'] as $route) {
        $this->get(route($route))->assertOk()->assertDontSee('Aqua Botol');
    }
    $this->type = WasteType::where('code', 'KARDUS')->firstOrFail();
    WasteMasterMutationGuard::run(fn () => $this->type->unit->forceFill(['is_active' => false])->save());
    foreach (['home', 'public.catalog', 'public.prices'] as $route) {
        $this->get(route($route))->assertOk()->assertDontSee('Kardus');
    }
});

it('creates a photographed type through the Admin form and supports the separate photo action', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('backoffice'));
    $this->actingAs($this->admin);
    Livewire::test(ManageWasteTypes::class)->callAction('create', data: [...$this->data, 'photo' => UploadedFile::fake()->image('new.jpg')])->assertHasNoErrors();
    $created = WasteType::where('code', 'BOTOL-BARU')->firstOrFail();
    expect($created->publicPhoto)->not->toBeNull();
    Livewire::test(ManageWasteTypes::class)->callTableAction('photo', $this->type, data: ['photo' => UploadedFile::fake()->image('quick.png')])->assertHasNoErrors();
    expect($this->type->fresh()->publicPhoto)->not->toBeNull();
});

it('preserves JPEG camera orientation while stripping EXIF', function (): void {
    if (! function_exists('exif_read_data')) {
        $this->markTestSkipped('EXIF extension is unavailable.');
    }
    $jpeg = UploadedFile::fake()->image('camera.jpg', 80, 40)->getContent();
    $exif = "Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0";
    $contents = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
    $photo = app(SaveWasteTypePhoto::class)->handle($this->admin, $this->type, UploadedFile::fake()->createWithContent('camera.jpg', $contents));
    $normalized = Storage::disk('waste_photos')->get($photo->path);
    $dimensions = getimagesizefromstring($normalized);
    expect([$dimensions[0], $dimensions[1]])->toBe([40, 80])->and($normalized)->not->toContain('Exif');
});
