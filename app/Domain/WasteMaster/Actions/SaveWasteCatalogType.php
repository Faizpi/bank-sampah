<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Actions;

use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class SaveWasteCatalogType
{
    public function __construct(private ManageWasteMaster $master, private SaveWasteTypePhoto $photos) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, ?WasteType $type, array $data): WasteType
    {
        return DB::transaction(function () use ($actor, $type, $data): WasteType {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $type = $type === null ? null : WasteType::query()->lockForUpdate()->findOrFail($type->id);
            Gate::forUser($actor)->authorize($type === null ? 'create' : 'update', $type ?? WasteType::class);
            $photo = $data['photo'] ?? null;
            if (($type === null && ! $photo instanceof UploadedFile) || ($photo !== null && ! $photo instanceof UploadedFile)) {
                throw ValidationException::withMessages(['photo' => 'Unggah foto JPG/PNG untuk jenis sampah baru.']);
            }
            $category = WasteCategory::query()->findOrFail($data['waste_category_id']);
            $unit = WasteUnit::query()->findOrFail($data['waste_unit_id']);
            $code = (string) $data['code'];
            $name = (string) $data['name'];
            $description = isset($data['education_description']) ? (string) $data['education_description'] : null;
            $order = (int) $data['sort_order'];
            $plastic = (bool) ($data['is_plastic'] ?? false);
            $active = (bool) ($data['is_active'] ?? true);
            $conditions = array_map('intval', $data['condition_ids'] ?? []);
            if ($type === null) {
                $type = $this->master->createType($actor, $category, $unit, $code, $name, $description, $order, $plastic, $active, $conditions);
            } else {
                $this->master->updateType($actor, $type, $category, $unit, $code, $name, $description, $order, $plastic, $active, $conditions);
            }
            if ($photo instanceof UploadedFile) {
                $this->photos->handle($actor, $type, $photo);
            }

            return $type->fresh('publicPhoto');
        });
    }
}
