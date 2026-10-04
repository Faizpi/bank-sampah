<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name', 120)->change();
            $table->string('email')->nullable()->change();
            $table->index('email', 'users_email_index');
            $table->string('phone', 20)->nullable()->unique('users_phone_unique');
            $table->string('status', 32)->default('menunggu_verifikasi')->index('users_status_index');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('terms_version', 40)->nullable();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->softDeletes();
        });
        $this->addChecks();
    }

    public function down(): void
    {
        $this->guardBaselineEmailRestoration();
        $this->dropChecks();
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['verified_by']);
            $table->dropIndex('users_status_index');
            $table->dropUnique('users_phone_unique');
            $table->dropIndex('users_email_index');
            $table->dropColumn(['phone', 'status', 'verified_at', 'verified_by', 'rejection_reason', 'last_login_at', 'terms_version', 'terms_accepted_at', 'deleted_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->unique('email', 'users_email_unique');
        });
    }

    private function guardBaselineEmailRestoration(): void
    {
        $invalidEmailCount = DB::table('users')
            ->whereNull('email')
            ->orWhereIn('email', DB::table('users')->select('email')->whereNotNull('email')->groupBy('email')->havingRaw('COUNT(*) > 1'))
            ->count();

        if ($invalidEmailCount > 0) {
            throw new RuntimeException('Cannot roll back identity users migration: users.email contains NULL or duplicate non-NULL values. Resolve these rows before restoring the baseline NOT NULL and UNIQUE constraints.');
        }
    }

    private function addChecks(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER users_identity_check_insert BEFORE INSERT ON users WHEN NEW.status NOT IN ('menunggu_verifikasi','aktif','ditolak','nonaktif') OR (NEW.status = 'ditolak' AND NEW.rejection_reason IS NULL) OR (NEW.terms_accepted_at IS NOT NULL AND NEW.terms_version IS NULL) BEGIN SELECT RAISE(ABORT, 'users identity check failed'); END");
            DB::unprepared("CREATE TRIGGER users_identity_check_update BEFORE UPDATE ON users WHEN NEW.status NOT IN ('menunggu_verifikasi','aktif','ditolak','nonaktif') OR (NEW.status = 'ditolak' AND NEW.rejection_reason IS NULL) OR (NEW.terms_accepted_at IS NOT NULL AND NEW.terms_version IS NULL) BEGIN SELECT RAISE(ABORT, 'users identity check failed'); END");

            return;
        }
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('menunggu_verifikasi','aktif','ditolak','nonaktif'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rejection_check CHECK (status <> 'ditolak' OR rejection_reason IS NOT NULL)");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_terms_check CHECK (terms_accepted_at IS NULL OR terms_version IS NOT NULL)');
    }

    private function dropChecks(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS users_identity_check_insert');
            DB::unprepared('DROP TRIGGER IF EXISTS users_identity_check_update');

            return;
        }
        foreach (['users_status_check', 'users_rejection_check', 'users_terms_check'] as $constraint) {
            DB::statement("ALTER TABLE users DROP CONSTRAINT {$constraint}");
        }
    }
};
