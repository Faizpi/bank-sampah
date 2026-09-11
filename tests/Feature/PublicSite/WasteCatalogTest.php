<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class WasteCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_shows_only_active_types_with_active_contextual_education(): void
    {
        $category = WasteCategory::factory()->create(['name' => 'Plastik']);
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $activeCondition = WasteCondition::factory()->create(['name' => 'Bersih']);
        $inactiveCondition = WasteCondition::factory()->inactive()->create(['name' => 'Kotor']);

        $activeType = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create([
            'name' => 'Botol PET',
            'education_description' => 'Bilas dan keringkan sebelum disetor.',
        ]);
        $inactiveType = WasteType::factory()->inactive()->for($category, 'category')->for($unit, 'unit')->create(['name' => 'Jenis Lama']);
        $inactiveCategory = WasteCategory::factory()->inactive()->create(['name' => 'Kategori Lama']);
        $hiddenType = WasteType::factory()->for($inactiveCategory, 'category')->for($unit, 'unit')->create(['name' => 'Jenis Tersembunyi']);

        $this->attachConditions($activeType, [$activeCondition, $inactiveCondition]);
        $this->attachConditions($inactiveType, [$activeCondition]);
        $this->attachConditions($hiddenType, [$activeCondition]);

        $response = $this->get(route('public.catalog'));

        $response->assertOk()
            ->assertSee('Botol PET')
            ->assertSee('Bilas dan keringkan sebelum disetor.')
            ->assertSee('Bersih')
            ->assertDontSee('Kotor')
            ->assertDontSee('Jenis Lama')
            ->assertDontSee('Jenis Tersembunyi');
    }

    public function test_public_catalog_has_no_media_download_or_private_storage_url(): void
    {
        Storage::fake('media_private');
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create();
        $media = Media::query()->create([
            'uuid' => (string) str()->uuid(),
            'disk' => 'media_private',
            'path' => 'waste/catalog-private.jpg',
            'original_name' => 'catalog-private.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1,
            'checksum' => 'private-checksum',
            'visibility' => MediaVisibility::Private,
        ]);
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['media_id' => $media->id]);
        $this->attachConditions($type, [$condition]);

        $response = $this->get(route('public.catalog'));

        $response->assertOk()
            ->assertDontSee('media_private')
            ->assertDontSee('catalog-private.jpg')
            ->assertDontSee('/storage/');
    }

    /** @param list<WasteCondition> $conditions */
    private function attachConditions(WasteType $type, array $conditions): void
    {
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync(array_map(static fn (WasteCondition $condition): int => $condition->id, $conditions)));
    }
}
