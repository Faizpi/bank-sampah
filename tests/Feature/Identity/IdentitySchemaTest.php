<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use RuntimeException;
use Tests\TestCase;

final class IdentitySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_tables_preserve_baseline_and_add_documented_columns(): void
    {
        self::assertTrue(Schema::hasColumns('users', ['id', 'name', 'email', 'email_verified_at', 'password', 'remember_token', 'phone', 'status', 'verified_at', 'verified_by', 'rejection_reason', 'last_login_at', 'terms_version', 'terms_accepted_at', 'deleted_at']));
        self::assertTrue(Schema::hasColumns('sessions', ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity', 'expires_at', 'ip_address_hash']));
        self::assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at', 'user_id', 'phone', 'expires_at', 'used_at']));
    }

    public function test_staff_service_area_migration_backfills_legacy_rows_and_is_idempotent(): void
    {
        Schema::dropIfExists('staff_service_areas');
        $area = DB::table('service_areas')->insertGetId(['name' => 'Area legacy', 'is_active' => true]);
        $legacyWithArea = User::factory()->create();
        $legacyWithoutArea = User::factory()->create();
        DB::table('staff_profiles')->insert([
            ['user_id' => $legacyWithArea->id, 'staff_number' => 'STF-LEGACY-AREA', 'service_area_id' => $area, 'active_from' => '2026-01-01', 'active_to' => '2026-12-31', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $legacyWithoutArea->id, 'staff_number' => 'STF-LEGACY-NULL', 'service_area_id' => null, 'active_from' => null, 'active_to' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $migration = require database_path('migrations/2026_08_15_120000_create_staff_service_areas_table.php');
        self::assertInstanceOf(Migration::class, $migration);
        $migration->up();
        $migration->up();

        $this->assertDatabaseCount('staff_service_areas', 1);
        $this->assertDatabaseHas('staff_service_areas', [
            'staff_profile_user_id' => $legacyWithArea->id,
            'service_area_id' => $area,
            'active_from' => '2026-01-01',
            'active_to' => '2026-12-31',
        ]);
        $this->assertDatabaseMissing('staff_service_areas', ['staff_profile_user_id' => $legacyWithoutArea->id]);
    }

    public function test_staff_service_area_schema_preserves_legacy_profile_columns_and_assignment_constraints(): void
    {
        self::assertTrue(Schema::hasColumns('staff_profiles', ['user_id', 'service_area_id', 'active_from', 'active_to']));
        self::assertTrue(Schema::hasColumns('staff_service_areas', ['staff_profile_user_id', 'service_area_id', 'active_from', 'active_to']));

        $profile = StaffProfile::factory()->create(['active_from' => '2026-07-30', 'active_to' => null]);
        $assignment = StaffServiceArea::query()
            ->where('staff_profile_user_id', $profile->user_id)
            ->where('service_area_id', $profile->service_area_id)
            ->sole();

        self::assertSame($profile->service_area_id, $assignment->service_area_id);
        $this->expectException(QueryException::class);
        StaffServiceArea::query()->create([
            'staff_profile_user_id' => $profile->user_id,
            'service_area_id' => $profile->service_area_id,
            'active_from' => '2026-07-30',
            'active_to' => null,
        ]);
    }

    public function test_email_is_nullable_and_nonunique_while_phone_is_unique_when_present(): void
    {
        User::factory()->count(2)->create(['phone' => null, 'email' => null]);
        User::factory()->count(2)->create(['phone' => null, 'email' => 'shared@example.test']);
        User::factory()->create(['phone' => '628123456789']);
        $this->expectException(QueryException::class);
        User::factory()->create(['phone' => '628123456789']);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert([
            'name' => 'Invalid Status',
            'email' => null,
            'password' => 'hash',
            'status' => 'tidak-valid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_terms_acceptance_without_version_is_rejected(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['terms_version' => null, 'terms_accepted_at' => now()]);
    }

    public function test_rejected_status_requires_a_reason(): void
    {
        $this->expectException(QueryException::class);
        User::factory()->create(['status' => 'ditolak', 'rejection_reason' => null]);
    }

    public function test_staff_active_date_order_is_checked(): void
    {
        $staff = StaffProfile::factory()->make(['active_from' => '2026-07-30', 'active_to' => '2026-07-29']);
        $this->expectException(QueryException::class);
        $staff->save();
    }

    public function test_user_checks_are_also_enforced_on_update(): void
    {
        $user = User::factory()->create();

        try {
            DB::table('users')->where('id', $user->id)->update(['status' => 'tidak-valid']);
            self::fail('Invalid status update was accepted.');
        } catch (QueryException) {
            self::assertDatabaseHas('users', ['id' => $user->id, 'status' => 'aktif']);
        }

        try {
            DB::table('users')->where('id', $user->id)->update(['status' => 'ditolak', 'rejection_reason' => null]);
            self::fail('Rejected update without reason was accepted.');
        } catch (QueryException) {
            self::assertDatabaseHas('users', ['id' => $user->id, 'status' => 'aktif']);
        }

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->update(['terms_version' => null, 'terms_accepted_at' => now()]);
    }

    public function test_staff_date_check_is_also_enforced_on_update(): void
    {
        $staff = StaffProfile::factory()->create(['active_from' => '2026-07-30', 'active_to' => null]);

        $this->expectException(QueryException::class);
        DB::table('staff_profiles')->where('user_id', $staff->user_id)->update(['active_to' => '2026-07-29']);
    }

    public function test_verified_by_sets_null_when_verifier_is_deleted(): void
    {
        $verifier = User::factory()->create();
        $verified = User::factory()->create(['verified_by' => $verifier->id]);
        $verifier->forceDelete();
        self::assertNull($verified->refresh()->verified_by);
    }

    public function test_profile_restricts_force_delete(): void
    {
        $profile = CustomerProfile::factory()->create();
        $this->expectException(QueryException::class);
        $profile->user->forceDelete();
    }

    public function test_customer_rt_is_restricted(): void
    {
        $customer = CustomerProfile::factory()->create();
        $rt = $customer->rt;

        try {
            $rt->delete();
            self::fail('Regional records must not be physically deleted.');
        } catch (LogicException) {
            self::assertDatabaseHas('rt', ['id' => $rt->id]);
            self::assertDatabaseHas('customer_profiles', ['user_id' => $customer->user_id, 'rt_id' => $rt->id]);
        }
    }

    public function test_staff_service_area_is_restricted(): void
    {
        $staff = StaffProfile::factory()->create();
        $serviceArea = $staff->serviceArea;

        try {
            $serviceArea->delete();
            self::fail('Regional records must not be physically deleted.');
        } catch (LogicException) {
            self::assertDatabaseHas('service_areas', ['id' => $serviceArea->id]);
            self::assertDatabaseHas('staff_profiles', ['user_id' => $staff->user_id, 'service_area_id' => $serviceArea->id]);
        }
    }

    public function test_staff_user_force_delete_is_restricted(): void
    {
        $staff = StaffProfile::factory()->create();

        $this->expectException(QueryException::class);
        $staff->user->forceDelete();
    }

    public function test_duplicate_reset_token_is_rejected_across_distinct_emails(): void
    {
        DB::table('password_reset_tokens')->insert(['email' => 'first@example.test', 'token' => 'same-hash']);

        $this->expectException(QueryException::class);
        DB::table('password_reset_tokens')->insert(['email' => 'second@example.test', 'token' => 'same-hash']);
    }

    public function test_technical_rows_cascade_on_force_delete(): void
    {
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'cascade', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => 1]);
        DB::table('password_reset_tokens')->insert(['email' => 'cascade@example.test', 'token' => 'hash', 'user_id' => $user->id]);
        $user->forceDelete();
        $this->assertDatabaseMissing('sessions', ['id' => 'cascade']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'cascade@example.test']);
    }

    public function test_identity_index_inventory_is_intentional(): void
    {
        $this->assertIndexInventory('users', [['email'], ['phone'], ['status']]);
        $this->assertIndexInventory('customer_profiles', [['customer_number'], ['rt_id'], ['joined_at'], ['qr_token_hash']]);
        $this->assertIndexInventory('staff_profiles', [['staff_number'], ['service_area_id', 'active_to']]);
        // Both session user indexes are intentional: baseline user-only lookup plus composite activity lookup.
        $this->assertIndexInventory('sessions', [['last_activity'], ['user_id'], ['user_id', 'last_activity'], ['expires_at'], ['ip_address_hash']]);
        // IMP-021 indexes technical lookup columns only; IMP-025 still owns reset-broker behavior.
        $this->assertIndexInventory('password_reset_tokens', [['user_id'], ['phone'], ['token'], ['expires_at'], ['used_at']]);
        self::assertContains(['token'], $this->uniqueIndexes('password_reset_tokens'));
    }

    public function test_rollback_refuses_unsafe_email_data_before_schema_mutation(): void
    {
        User::factory()->create(['email' => null]);
        try {
            $this->rollbackIdentityMigration();
            self::fail('Unsafe rollback must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame($this->guardMessage(), $exception->getMessage());
        }
        self::assertTrue(Schema::hasColumn('users', 'phone'));
        self::assertDatabaseHas('users', ['email' => null]);
    }

    public function test_rollback_refuses_duplicate_email_without_mutating_rows(): void
    {
        User::factory()->count(2)->create(['email' => 'duplicate@example.test']);
        try {
            $this->rollbackIdentityMigration();
            self::fail('Unsafe rollback must fail.');
        } catch (RuntimeException $exception) {
            self::assertSame($this->guardMessage(), $exception->getMessage());
        }
        self::assertSame(2, DB::table('users')->where('email', 'duplicate@example.test')->count());
        self::assertTrue(Schema::hasColumn('users', 'phone'));
    }

    public function test_baseline_compatible_rows_allow_rollback(): void
    {
        User::factory()->create(['email' => 'one@example.test']);
        User::factory()->create(['email' => 'two@example.test']);
        $this->rollbackIdentityMigration();

        self::assertFalse(Schema::hasColumn('users', 'phone'));
        self::assertFalse((bool) collect(Schema::getColumns('users'))->firstWhere('name', 'email')['nullable']);
        self::assertContains(['email'], $this->uniqueIndexes('users'));
        self::assertTrue(Schema::hasColumns('password_reset_tokens', ['email', 'token', 'created_at']));
    }

    /** @param list<list<string>> $expected */
    private function assertIndexInventory(string $table, array $expected): void
    {
        $normalize = static function (array $indexes): array {
            $normalized = array_map(static function (array $columns): array {
                sort($columns);

                return $columns;
            }, $indexes);
            sort($normalized);

            return $normalized;
        };

        self::assertSame($normalize($expected), $normalize($this->namedIndexes($table)));
    }

    /** @return list<list<string>> */
    private function namedIndexes(string $table): array
    {
        return array_values(array_map(
            fn (object $index): array => array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name'),
            array_filter(DB::select("PRAGMA index_list('{$table}')"), static fn (object $index): bool => $index->origin !== 'pk'),
        ));
    }

    /** @return list<list<string>> */
    private function uniqueIndexes(string $table): array
    {
        return array_values(array_map(fn (object $index): array => array_column(DB::select("PRAGMA index_info('{$index->name}')"), 'name'), array_filter(DB::select("PRAGMA index_list('{$table}')"), static fn (object $index): bool => (int) $index->unique === 1)));
    }

    private function rollbackIdentityMigration(): void
    {
        $migration = require database_path('migrations/2026_07_30_120000_alter_users_for_identity.php');
        $migration->down();
    }

    private function guardMessage(): string
    {
        return 'Cannot roll back identity users migration: users.email contains NULL or duplicate non-NULL values. Resolve these rows before restoring the baseline NOT NULL and UNIQUE constraints.';
    }
}
