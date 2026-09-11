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
        $this->revokeNonCitizenRequestPermission();

        if (! Schema::hasColumn('grocery_redemptions', 'source_type')) {
            return;
        }

        if (DB::table('grocery_redemptions')->where('source_type', 'bantuan_gratis')->exists()) {
            throw new RuntimeException('Migrasi sumber sembako dihentikan untuk melindungi histori bantuan gratis. Gunakan reset data lokal atau remediasi transaksi lama sebelum menjalankan migrasi ini.');
        }

        Schema::table('grocery_redemptions', function (Blueprint $table): void {
            $table->dropColumn('source_type');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('grocery_redemptions', 'source_type')) {
            Schema::table('grocery_redemptions', function (Blueprint $table): void {
                $table->string('source_type', 30)->default('saldo');
            });
        }
    }

    private function revokeNonCitizenRequestPermission(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'grocery.request')->value('id');

        if (! is_numeric($permissionId)) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', ['petugas', 'admin', 'superadmin'])
            ->pluck('id');

        if ($roleIds->isNotEmpty()) {
            DB::table('permission_role')
                ->where('permission_id', (int) $permissionId)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
