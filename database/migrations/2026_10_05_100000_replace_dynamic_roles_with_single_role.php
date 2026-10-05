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
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 16)->nullable());
        foreach (DB::table('users')->select('id')->get() as $user) {
            $roles = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('user_id', $user->id)->pluck('name');
            if ($roles->count() !== 1) {
                throw new RuntimeException('Cutover requires exactly one existing role per user; use the guarded demo reset for dummy data.');
            }
            $role = $roles->first() === 'superadmin' ? 'admin' : $roles->first();
            if (! in_array($role, ['warga', 'petugas', 'bendahara', 'admin'], true)) {
                throw new RuntimeException('Unknown role; cutover cannot infer account authority.');
            }
            DB::table('users')->where('id', $user->id)->update(['role' => $role]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->string('role', 16)->nullable(false)->change());
        if (DB::getDriverName() === 'sqlite') {
            foreach (['INSERT', 'UPDATE'] as $event) {
                $name = strtolower($event);
                DB::unprepared("CREATE TRIGGER users_identity_check_{$name} BEFORE {$event} ON users WHEN NEW.status NOT IN ('menunggu_verifikasi','aktif','ditolak','nonaktif') OR (NEW.status = 'ditolak' AND NEW.rejection_reason IS NULL) OR (NEW.terms_accepted_at IS NOT NULL AND NEW.terms_version IS NULL) BEGIN SELECT RAISE(ABORT, 'users identity check failed'); END");
            }
            foreach (['INSERT', 'UPDATE'] as $event) {
                DB::unprepared("CREATE TRIGGER users_role_{$event} BEFORE {$event} ON users WHEN NEW.role IS NULL OR NEW.role NOT IN ('warga','petugas','bendahara','admin') BEGIN SELECT RAISE(ABORT, 'invalid user role'); END");
            }
        } else {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (CAST(role AS BINARY) IN ('warga','petugas','bendahara','admin'))");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_canonical_check CHECK (CAST(status AS BINARY) IN ('menunggu_verifikasi','aktif','ditolak','nonaktif'))");
        }
        Schema::create('access_role_locks', function (Blueprint $table): void {
            $table->string('name', 16)->primary();
        });
        DB::table('access_role_locks')->insert(['name' => 'admin']);
        Schema::drop('role_user');
        Schema::drop('permission_role');
        Schema::drop('roles');
        Schema::drop('permissions');
    }

    public function down(): void
    {
        throw new RuntimeException('Clean cutover is irreversible; restore the checkpoint and its database backup together.');
    }
};
