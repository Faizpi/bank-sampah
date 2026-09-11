<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->insertOrIgnore([
            'name' => 'media.retention.execute',
            'description' => 'Menjalankan retensi foto penjemputan.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('permissions')->where('name', 'media.retention.execute')->update([
            'description' => 'Menjalankan retensi foto penjemputan.',
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('name', 'media.retention.execute')->value('id');
        $superadminRoleId = DB::table('roles')->where('name', 'superadmin')->value('id');

        if ($permissionId !== null && $superadminRoleId !== null) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $superadminRoleId,
                'granted_by' => null,
                'reason' => 'Permission teknis bawaan aplikasi.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'media.retention.execute')->delete();
    }
};
