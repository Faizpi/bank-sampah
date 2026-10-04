<?php

declare(strict_types=1);

namespace App\Models;

use App\Authorization\PermissionChecker;
use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\DatabaseSession;
use App\Domain\Identity\Models\PasswordResetToken;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\TermsAcceptanceHistory;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserStatus $status
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable|null $verified_at
 * @property CarbonImmutable|null $last_login_at
 * @property CarbonImmutable|null $terms_accepted_at
 * @property CarbonImmutable|null $deleted_at
 */
#[Fillable(['name', 'phone', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'phone', 'email', 'terms_version', 'terms_accepted_at'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'backoffice'
            && app(PermissionChecker::class)->allows($this, 'backoffice.access');
    }

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'terms_accepted_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_by', 'reason'])
            ->withTimestamps();
    }

    /** @return HasOne<CustomerProfile, $this> */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /** @return HasOne<StaffProfile, $this> */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    /** @return HasOne<LedgerAccount, $this> */
    public function ledgerAccount(): HasOne
    {
        return $this->hasOne(LedgerAccount::class);
    }

    /** @return HasMany<Deposit, $this> */
    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class, 'customer_id');
    }

    /** @return HasMany<PickupRequest, $this> */
    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'customer_id');
    }

    /** @return HasMany<PickupRequest, $this> */
    public function assignedPickups(): HasMany
    {
        return $this->hasMany(PickupRequest::class, 'assigned_staff_id');
    }

    /** @return HasMany<WithdrawalRequest, $this> */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'customer_id');
    }

    /** @return HasMany<WithdrawalRequest, $this> */
    public function requestedWithdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'requested_by_id');
    }

    /** @return HasMany<WithdrawalRequest, $this> */
    public function assignedWithdrawals(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'payer_id');
    }

    /** @return HasMany<GroceryRedemption, $this> */
    public function groceryRedemptions(): HasMany
    {
        return $this->hasMany(GroceryRedemption::class, 'customer_id');
    }

    /** @return HasMany<GroceryRedemption, $this> */
    public function requestedGroceries(): HasMany
    {
        return $this->hasMany(GroceryRedemption::class, 'requested_by_id');
    }

    /** @return HasMany<GroceryRedemption, $this> */
    public function preparedGroceries(): HasMany
    {
        return $this->hasMany(GroceryRedemption::class, 'prepared_by_id');
    }

    /** @return HasMany<GroceryRedemption, $this> */
    public function handedOverGroceries(): HasMany
    {
        return $this->hasMany(GroceryRedemption::class, 'handover_actor_id');
    }

    /** @return HasMany<TransactionCorrection, $this> */
    public function transactionCorrections(): HasMany
    {
        return $this->hasMany(TransactionCorrection::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(self::class, 'verified_by');
    }

    /** @return HasMany<User, $this> */
    public function verifiedUsers(): HasMany
    {
        return $this->hasMany(self::class, 'verified_by');
    }

    /** @return HasMany<DatabaseSession, $this> */
    public function databaseSessions(): HasMany
    {
        return $this->hasMany(DatabaseSession::class);
    }

    /** @return HasMany<PasswordResetToken, $this> */
    public function passwordResetTokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    /** @return HasMany<TermsAcceptanceHistory, $this> */
    public function termsAcceptanceHistories(): HasMany
    {
        return $this->hasMany(TermsAcceptanceHistory::class);
    }
}
