<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'pickup.complete')->value('id');
        $roleId = DB::table('roles')->where('name', 'petugas')->value('id');

        if ($permissionId === null || $roleId === null) {
            return;
        }

        $now = now();

        DB::table('permission_role')->insertOrIgnore([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
            'granted_by' => null,
            'reason' => 'Pemulihan permission baseline petugas untuk finalisasi penjemputan.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'pickup.complete')->value('id');
        $roleId = DB::table('roles')->where('name', 'petugas')->value('id');

        if ($permissionId === null || $roleId === null) {
            return;
        }

        DB::table('permission_role')
            ->where('permission_id', $permissionId)
            ->where('role_id', $roleId)
            ->delete();
    }
};
