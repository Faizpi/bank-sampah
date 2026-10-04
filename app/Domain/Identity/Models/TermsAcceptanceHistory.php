<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Models\User;
use Database\Factories\TermsAcceptanceHistoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TermsAcceptanceHistory extends Model
{
    /** @use HasFactory<TermsAcceptanceHistoryFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'accepted_version', 'accepted_at'];

    protected static function newFactory(): TermsAcceptanceHistoryFactory
    {
        return TermsAcceptanceHistoryFactory::new();
    }

    protected function casts(): array
    {
        return ['accepted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
