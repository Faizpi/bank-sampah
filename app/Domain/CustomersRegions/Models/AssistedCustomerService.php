<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['owner_id', 'operator_id', 'service_type', 'consent_version', 'consented_at', 'evidence_media_id'])]
#[Hidden(['consent_version'])]
final class AssistedCustomerService extends Model
{
    protected function casts(): array
    {
        return ['owner_id' => 'integer', 'operator_id' => 'integer', 'evidence_media_id' => 'integer', 'consented_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'evidence_media_id');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Layanan berbantuan dan buktinya tidak dapat dihapus secara operasional.');
    }
}
