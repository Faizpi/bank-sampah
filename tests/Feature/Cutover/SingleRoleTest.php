<?php

declare(strict_types=1);

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

it('seeds exactly four isolated roles and no transactions', function (): void {
    $this->seed(WorkflowFixture::class);
    expect(User::count())->toBe(4)
        ->and(User::pluck('role')->map(fn ($role) => $role->value)->sort()->values()->all())->toBe(['admin', 'bendahara', 'petugas', 'warga']);
    foreach (['roles', 'permissions', 'role_user', 'permission_role', 'assisted_customer_services'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    foreach (['deposits', 'withdrawal_requests', 'grocery_redemptions', 'pickup_requests', 'ledger_entries'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
});

it('rejects noncanonical role values at the database boundary', function (?string $role): void {
    $user = User::factory()->create();
    expect(fn () => DB::table('users')->where('id', $user->id)->update(['role' => $role]))->toThrow(QueryException::class);
})->with([null, 'unknown', 'ADMIN', 'admin ', 'petugas,bendahara']);

it('does not allow admin to perform routine physical service', function (string $permission): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    expect(app(PermissionChecker::class)->allows($admin, $permission))->toBeFalse();
})->with(['deposit.create', 'deposit.finalize', 'pickup.execute', 'pickup.complete', 'withdrawal.pay', 'grocery.prepare', 'grocery.handover']);

it('preserves the last active admin during role status and delete mutations', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    expect(fn () => app(ChangeUserRole::class)->handle($admin, $admin, 'petugas', 'Pergantian jabatan pengurus.'))->toThrow(ValidationException::class);
    expect(fn () => $admin->fresh()->forceFill(['status' => UserStatus::Inactive])->save())->toThrow(ValidationException::class);
    expect(fn () => $admin->fresh()->delete())->toThrow(ValidationException::class);
    expect(fn () => User::whereKey($admin->id)->update(['status' => UserStatus::Inactive]))->toThrow(LogicException::class);
    expect(fn () => DB::table('users')->where('id', $admin->id)->update(['role' => 'petugas']))->toThrow(QueryException::class);
    expect(DB::table('access_role_locks')->where('name', 'admin')->value('active_admin_count'))->toBe(1);
    expect($admin->fresh()->status)->toBe(UserStatus::Active);
});
