<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

final class RolesAndPermissionsSeeder extends Seeder
{
    /** @var array<string, string> */
    private const PERMISSIONS = [
        'profile.view' => 'Melihat profil dalam scope.', 'profile.update' => 'Mengubah profil dalam scope.', 'backoffice.access' => 'Masuk ke panel teknis back-office Filament saja.',
        'user.view' => 'Mengaktifkan akses baca pengguna yang dibatasi permission scope eksplisit.', 'user.view.area' => 'Melihat pengguna aktif: diri sendiri dan nasabah aktif pada RT area pelayanan aktif petugas yang efektif hari ini.', 'user.view.all' => 'Melihat seluruh pengguna aktif.', 'user.create' => 'Membuat pengguna.', 'user.update' => 'Memperbarui pengguna.', 'user.activate' => 'Mengaktifkan pengguna.', 'user.verify' => 'Memverifikasi warga.', 'user.reject' => 'Menolak verifikasi warga.', 'user.reset-password' => 'Memulai reset kata sandi administratif.', 'role.view' => 'Melihat role dan permission.', 'role.manage' => 'Mengelola role dan permission.', 'session.revoke' => 'Mengakhiri sesi pengguna.',
        'customer.view' => 'Melihat nasabah.', 'customer.create-assisted' => 'Membuat nasabah dengan layanan berbantuan.', 'customer.update' => 'Memperbarui nasabah.', 'customer.card.issue' => 'Menerbitkan kartu nasabah.', 'customer.qr.rotate' => 'Merotasi token QR nasabah.', 'region.view' => 'Melihat wilayah.', 'region.manage' => 'Mengelola wilayah.', 'waste.view' => 'Melihat master sampah.', 'waste.manage' => 'Mengelola master sampah.', 'price.view' => 'Melihat harga.', 'price.manage' => 'Mengelola periode harga.',
        'deposit.view' => 'Melihat setoran.', 'deposit.create' => 'Membuat setoran.', 'deposit.update-draft' => 'Memperbarui draf setoran.', 'deposit.finalize' => 'Memfinalisasi setoran.', 'transaction.correct' => 'Membuat koreksi transaksi final.', 'transaction.reverse' => 'Membuat reversal transaksi final.', 'ledger.view' => 'Melihat ledger.', 'ledger.adjust' => 'Membuat penyesuaian ledger.', 'correction.view-customer' => 'Melihat koreksi aman bagi warga.',
        'pickup.view' => 'Melihat penjemputan.', 'pickup.request' => 'Membuat pengajuan penjemputan.', 'pickup.review' => 'Meninjau penjemputan.', 'pickup.schedule' => 'Menjadwalkan penjemputan.', 'pickup.execute' => 'Menjalankan penjemputan.', 'pickup.complete' => 'Menyelesaikan penjemputan.', 'pickup.cancel' => 'Membatalkan penjemputan.', 'pickup.capacity.manage' => 'Mengelola kapasitas penjemputan.', 'withdrawal.request' => 'Membuat pencairan.', 'withdrawal.view' => 'Melihat pencairan.', 'withdrawal.approve' => 'Menyetujui pencairan.', 'withdrawal.pay' => 'Membayar pencairan.', 'withdrawal.cancel' => 'Membatalkan pencairan.', 'grocery.package.view' => 'Melihat paket sembako.', 'grocery.package.manage' => 'Mengelola paket sembako.', 'grocery.request' => 'Membuat penukaran sembako.', 'grocery.view' => 'Melihat penukaran sembako.', 'grocery.approve' => 'Menyetujui penukaran sembako.', 'grocery.prepare' => 'Menyiapkan paket sembako.', 'grocery.handover' => 'Menyerahkan paket sembako.', 'grocery.cancel' => 'Membatalkan penukaran sembako.',
        'notification.view' => 'Melihat notifikasi sendiri.', 'announcement.view' => 'Melihat pengumuman.', 'announcement.manage' => 'Mengelola pengumuman.', 'announcement.publish' => 'Menerbitkan pengumuman.', 'mobile-service.view' => 'Melihat layanan keliling.', 'mobile-service.manage' => 'Mengelola layanan keliling.', 'mobile-service.operate' => 'Mengoperasikan layanan keliling.', 'target.view' => 'Melihat target.', 'target.manage' => 'Mengelola target.', 'target.publish' => 'Menerbitkan target.', 'statistics.internal.view' => 'Melihat statistik internal.', 'statistics.public.manage' => 'Mengelola publikasi statistik.', 'qr-verification.rotate' => 'Merotasi token verifikasi QR.',
        'report.view' => 'Melihat laporan.', 'report.export' => 'Mengekspor laporan.', 'audit.view' => 'Melihat audit log tersanitasi.', 'reconciliation.view' => 'Melihat rekonsiliasi.', 'reconciliation.create' => 'Membuat rekonsiliasi.', 'reconciliation.approve' => 'Mengesahkan rekonsiliasi.', 'system.settings.manage' => 'Mengelola pengaturan sistem.', 'system.maintenance' => 'Menjalankan maintenance sistem.', 'backup.run' => 'Menjalankan backup.', 'backup.view' => 'Melihat backup.', 'backup.restore' => 'Memulihkan backup.', 'audit.retention.execute' => 'Menjalankan retensi audit.',
    ];

    /** @var list<string> Permissions shared by the admin role. */
    private const ADMIN_PERMISSIONS = [
        'profile.view', 'profile.update', 'backoffice.access', 'user.view', 'user.view.all', 'user.create', 'user.update', 'user.activate', 'user.verify', 'user.reject', 'user.reset-password', 'role.view', 'session.revoke', 'customer.view', 'customer.create-assisted', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.view', 'region.manage', 'waste.view', 'waste.manage', 'price.view', 'price.manage', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'pickup.capacity.manage', 'withdrawal.request', 'withdrawal.view', 'withdrawal.approve', 'withdrawal.pay', 'withdrawal.cancel', 'grocery.package.view', 'grocery.package.manage', 'grocery.request', 'grocery.view', 'grocery.approve', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'notification.view', 'announcement.view', 'announcement.manage', 'announcement.publish', 'mobile-service.view', 'mobile-service.manage', 'mobile-service.operate', 'target.view', 'target.manage', 'target.publish', 'statistics.internal.view', 'statistics.public.manage', 'qr-verification.rotate', 'report.view', 'report.export', 'audit.view', 'reconciliation.view', 'reconciliation.create', 'reconciliation.approve',
    ];

    /** @var list<string> Technical/system permissions unique to the superadmin role. */
    private const SUPERADMIN_TECHNICAL_PERMISSIONS = [
        'role.manage', 'system.settings.manage', 'system.maintenance', 'backup.run', 'backup.view', 'backup.restore', 'audit.retention.execute',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'warga' => ['profile.view', 'profile.update', 'customer.view', 'customer.update', 'region.view', 'waste.view', 'price.view', 'deposit.view', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.cancel', 'withdrawal.request', 'withdrawal.view', 'withdrawal.cancel', 'grocery.package.view', 'grocery.request', 'grocery.view', 'grocery.cancel', 'notification.view', 'announcement.view', 'mobile-service.view', 'target.view'],
        'petugas' => ['profile.view', 'profile.update', 'user.view', 'user.view.area', 'customer.view', 'customer.create-assisted', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.view', 'waste.view', 'price.view', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'withdrawal.request', 'withdrawal.view', 'withdrawal.pay', 'grocery.package.view', 'grocery.request', 'grocery.view', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'notification.view', 'announcement.view', 'mobile-service.view', 'mobile-service.operate', 'target.view', 'statistics.internal.view', 'report.view', 'reconciliation.view', 'reconciliation.create'],
        'bendahara' => ['profile.view', 'profile.update', 'user.view', 'user.view.area', 'customer.view', 'region.view', 'waste.view', 'price.view', 'ledger.view', 'correction.view-customer', 'withdrawal.request', 'withdrawal.view', 'withdrawal.pay', 'withdrawal.cancel', 'grocery.package.view', 'notification.view', 'announcement.view', 'mobile-service.view', 'target.view', 'statistics.internal.view', 'report.view', 'report.export', 'reconciliation.view', 'reconciliation.create', 'reconciliation.approve'],
        'admin' => self::ADMIN_PERMISSIONS,
        'superadmin' => [...self::ADMIN_PERMISSIONS, ...self::SUPERADMIN_TECHNICAL_PERMISSIONS],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::query()->updateOrCreate(['name' => $name], ['description' => $description]);
        }

        foreach (self::ROLE_PERMISSIONS as $name => $permissions) {
            $role = Role::query()->updateOrCreate(['name' => $name], ['description' => ucfirst($name)]);
            $role->permissions()->sync(Permission::query()->whereIn('name', $permissions)->pluck('id'));
        }
    }
}
