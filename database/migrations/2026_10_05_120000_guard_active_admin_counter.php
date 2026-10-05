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
        Schema::table('access_role_locks', fn (Blueprint $table) => $table->unsignedInteger('active_admin_count')->default(0));
        DB::table('access_role_locks')->where('name', 'admin')->update(['active_admin_count' => DB::table('users')->where('role', 'admin')->where('status', 'aktif')->whereNull('deleted_at')->count()]);
        $oldActive = "OLD.role = 'admin' AND OLD.status = 'aktif' AND OLD.deleted_at IS NULL";
        $newActive = "NEW.role = 'admin' AND NEW.status = 'aktif' AND NEW.deleted_at IS NULL";
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER users_admin_insert AFTER INSERT ON users WHEN {$newActive} BEGIN UPDATE access_role_locks SET active_admin_count = active_admin_count + 1 WHERE name = 'admin'; END");
            foreach (['delete' => $oldActive, 'update' => "({$oldActive}) AND NOT ({$newActive})"] as $event => $condition) {
                $sqlEvent = strtoupper($event);
                DB::unprepared("CREATE TRIGGER users_admin_{$event}_reduce BEFORE {$sqlEvent} ON users WHEN {$condition} BEGIN SELECT CASE WHEN (SELECT active_admin_count FROM access_role_locks WHERE name = 'admin') <= 1 THEN RAISE(ABORT, 'last active admin') END; UPDATE access_role_locks SET active_admin_count = active_admin_count - 1 WHERE name = 'admin'; END");
            }
            DB::unprepared("CREATE TRIGGER users_admin_update_add AFTER UPDATE ON users WHEN NOT ({$oldActive}) AND ({$newActive}) BEGIN UPDATE access_role_locks SET active_admin_count = active_admin_count + 1 WHERE name = 'admin'; END");

            return;
        }
        DB::unprepared("CREATE TRIGGER users_admin_insert BEFORE INSERT ON users FOR EACH ROW BEGIN IF {$newActive} THEN UPDATE access_role_locks SET active_admin_count = active_admin_count + 1 WHERE name = 'admin'; END IF; END");
        $reduce = "UPDATE access_role_locks SET active_admin_count = active_admin_count - 1 WHERE name = 'admin' AND active_admin_count > 1; IF ROW_COUNT() = 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'last active admin'; END IF;";
        DB::unprepared("CREATE TRIGGER users_admin_update BEFORE UPDATE ON users FOR EACH ROW BEGIN IF ({$oldActive}) AND NOT ({$newActive}) THEN {$reduce} ELSEIF NOT ({$oldActive}) AND ({$newActive}) THEN UPDATE access_role_locks SET active_admin_count = active_admin_count + 1 WHERE name = 'admin'; END IF; END");
        DB::unprepared("CREATE TRIGGER users_admin_delete BEFORE DELETE ON users FOR EACH ROW BEGIN IF {$oldActive} THEN {$reduce} END IF; END");
    }

    public function down(): void
    {
        throw new RuntimeException('Restore the entire pre-cutover database checkpoint rather than dropping access invariants.');
    }
};
