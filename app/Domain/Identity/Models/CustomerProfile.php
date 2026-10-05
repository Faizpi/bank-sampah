<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\CustomersRegions\Contracts\CustomerNumber;
use App\Domain\CustomersRegions\Models\Rt;
use App\Models\User;
use Database\Factories\CustomerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerProfile extends Model
{
    /** @use HasFactory<CustomerProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'customer_number', 'rt_id', 'address', 'joined_at', 'qr_token_hash', 'qr_token_encrypted', 'qr_rotated_at'];

    protected $hidden = ['address', 'qr_token_hash', 'qr_token_encrypted'];

    protected static function newFactory(): CustomerProfileFactory
    {
        return CustomerProfileFactory::new();
    }

    protected function casts(): array
    {
        return ['joined_at' => 'immutable_date', 'qr_token_encrypted' => 'encrypted', 'qr_rotated_at' => 'immutable_datetime'];
    }

    public function customerNumber(): CustomerNumber
    {
        return CustomerNumber::fromString((string) $this->customer_number);
    }

    public function serviceRegion(): ?string
    {
        $rt = $this->relationLoaded('rt') ? $this->rt : $this->rt()->with('rw.dusun')->first();
        if (! $rt instanceof Rt) {
            return null;
        }

        $rt->loadMissing('rw.dusun');
        $parts = [
            $rt->name,
            $rt->rw?->name,
            $rt->rw?->dusun?->name,
        ];

        $region = implode(', ', array_values(array_filter($parts, static fn (?string $part): bool => filled($part))));

        return $region !== '' ? $region : null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }
}
