# Role dan Permission

## 1. Prinsip otorisasi

1. Role adalah paket permission awal, bukan pengganti pemeriksaan permission.
2. Setiap request memeriksa autentikasi, permission tindakan, scope record, status record, dan separation of duties.
3. Menyembunyikan menu tidak dianggap sebagai kontrol keamanan. Policy/service tetap menolak akses langsung.
4. Scope default adalah paling sempit: `own`, `assigned`, `area`, atau `all`; untuk record pengguna, scope harus diberikan secara eksplisit melalui `user.view.area` atau `user.view.all`.
5. Superadmin mengelola aspek teknis dan akses, tetapi **tidak otomatis** memiliki `ledger.adjust`, `transaction.correct`, `withdrawal.approve`, `withdrawal.pay`, `grocery.approve`, atau `grocery.handover`.
6. Permission sensitif hanya diberikan melalui keputusan pengelola dan dicatat dalam audit log.
7. Editor role/permission (RoleResource & PermissionResource) tersedia di panel back-office dan dibatasi `role.view`/`role.manage`: mutasi menyimpan metadata `granted_by` dan `reason`, dan role sistem (`warga`, `petugas`, `bendahara`, `admin`, `superadmin`) tidak dapat dihapus. Katalog permission tetap sumber otoritatif.

Kode matriks: `O` own, `A` area, `X` semua record aktif, `—` tidak diberikan secara default. Pada permission pengguna, `user.view` adalah izin dasar dan tanda `*` berarti scope record harus dilengkapi oleh `user.view.area` atau `user.view.all`; tanpa scope eksplisit sistem fail-closed ke diri sendiri. Nilai matriks adalah baseline role; assignment dapat dipersempit, tidak diperluas tanpa perubahan resmi.

## 2. Definisi role

| Role | Tanggung jawab utama | Batas utama |
|---|---|---|
| `warga` | Mengelola akun sendiri, melihat saldo/riwayat, dan membuat pengajuan. | Hanya record sendiri; tidak dapat membuat mutasi atau keputusan administratif. |
| `petugas` | Setoran, penjemputan, layanan keliling, layanan berbantuan, pembayaran/penyerahan bila ditugaskan. | Scope penugasan/area; tidak mengubah harga, role, atau saldo langsung. |
| `bendahara` | Pembayaran pencairan yang telah disetujui, bukti, kas, dan rekonsiliasi. | Tidak menyetujui pencairan secara default dan tidak mengoreksi saldo. |
| `admin` | Operasional, master data, verifikasi, persetujuan, laporan, koreksi bila permission khusus diberikan. | Tidak mengelola konfigurasi teknis berisiko tinggi atau melewati mekanisme ledger. |
| `superadmin` | Konfigurasi teknis, role/permission, status sistem, backup, restore, dan retensi teknis. | Tidak otomatis berwenang melakukan tindakan keuangan operasional. |

## 3. Katalog permission granular

### Akun dan akses

| Permission | Arti |
|---|---|
| `profile.view` / `profile.update` | Melihat/mengubah profil dalam scope. |
| `user.view` | Izin dasar untuk membuka data pengguna; tidak memberi scope record dengan sendirinya. Tanpa `user.view.area` atau `user.view.all`, akses pengguna lain gagal tertutup dan hanya diri sendiri yang terlihat. |
| `user.view.area` | Bersama `user.view`, untuk aktor dengan `StaffProfile` aktif efektif hari ini, `service_area` tidak null dan aktif, serta RT pivot area aktif: melihat diri sendiri dan pengguna aktif yang merupakan nasabah pada RT tersebut. Tidak memberi akses ke staf atau pengguna tanpa profil nasabah. |
| `user.view.all` | Bersama `user.view`, melihat seluruh pengguna aktif. |
| `user.create` / `user.update` / `user.activate` | Kelola pengguna sesuai scope record yang sama; permission tindakan tetap wajib. |
| `user.verify` / `user.reject` | Memutuskan verifikasi warga; penolakan wajib alasan. |
| `user.reset-password` | Mengubah langsung kata sandi pengguna target yang benar-benar lupa kata sandi dan tidak dapat login, setelah metode verifikasi `tatap_muka`/`callback_nomor_terdaftar` serta alasan 10–1000 karakter tervalidasi; tidak dapat menargetkan diri sendiri. |
| `role.view` / `role.manage` | Melihat atau mengelola role dan permission. |
| `session.revoke` | Mengakhiri sesi pengguna target sesuai kewenangan; wajib bersama `user.reset-password` untuk perubahan kata sandi administratif. |
| `backoffice.access` | Mengizinkan masuk ke panel teknis back-office Filament saja. Bukan permission domain/bisnis, tidak memberi akses resource, action, data, atau bypass policy. Baseline diberikan kepada `admin` dan `superadmin` (superadmin mewarisi seluruh hak admin ditambah permission teknis). |

### Nasabah, wilayah, dan master

| Permission | Arti |
|---|---|
| `customer.view` / `customer.create-assisted` / `customer.update` | Kelola nasabah, termasuk layanan berbantuan. |
| `customer.card.issue` / `customer.qr.rotate` | Menerbitkan kartu atau merotasi token QR. |
| `region.view` / `region.manage` | Melihat/mengelola dusun, RW, RT, area pelayanan. |
| `waste.view` / `waste.manage` | Melihat/mengelola kategori, jenis, satuan, kondisi, foto, edukasi. |
| `price.view` / `price.manage` | Melihat atau membuat periode harga. |

### Setoran dan saldo

| Permission | Arti |
|---|---|
| `deposit.view` / `deposit.create` / `deposit.update-draft` | Melihat, membuat, atau mengubah draf setoran. |
| `deposit.finalize` | Finalisasi setoran dan efek ledger atomik. |
| `transaction.correct` | Membuat koreksi transaksi final. |
| `transaction.reverse` | Membuat reversal transaksi final. |
| `ledger.view` | Melihat ledger dalam scope. |
| `ledger.adjust` | Membuat penyesuaian ledger resmi, bukan edit saldo. |
| `correction.view-customer` | Melihat versi koreksi yang aman bagi warga. |

### Penjemputan, pencairan, dan sembako

| Permission | Arti |
|---|---|
| `pickup.view` / `pickup.request` / `pickup.review` / `pickup.schedule` | Pengajuan dan pengelolaan penjemputan. |
| `pickup.execute` / `pickup.complete` / `pickup.cancel` | Menjalankan, menyelesaikan, atau membatalkan sesuai status. |
| `pickup.capacity.manage` | Mengatur kapasitas harian. |
| `withdrawal.request` / `withdrawal.view` | Membuat/melihat pencairan. |
| `withdrawal.approve` | Menyetujui/menolak pencairan; terpisah dari pembayaran. |
| `withdrawal.pay` | Memverifikasi penerima, membayar, dan mengunggah bukti. |
| `withdrawal.cancel` | Membatalkan sesuai state machine. |
| `grocery.package.view` / `grocery.package.manage` | Melihat/mengelola definisi paket tanpa stok rinci. |
| `grocery.request` / `grocery.view` | Membuat/melihat penukaran. |
| `grocery.approve` | Menyetujui setelah cek ketersediaan manual. |
| `grocery.prepare` | Menandai persiapan paket. |
| `grocery.handover` | Memverifikasi dan menyerahkan paket dengan bukti. |
| `grocery.cancel` | Membatalkan sesuai state machine. |

### Program, komunikasi, dan publik

| Permission | Arti |
|---|---|
| `notification.view` | Melihat notifikasi sendiri. |
| `announcement.view` / `announcement.manage` / `announcement.publish` | Kelola dan terbitkan pengumuman. |
| `mobile-service.view` / `mobile-service.manage` / `mobile-service.operate` | Jadwal dan operasi layanan keliling. |
| `target.view` / `target.manage` / `target.publish` | Kelola target dan publikasi progres. |
| `statistics.internal.view` | Melihat agregat internal RT/RW. |
| `statistics.public.manage` | Mengatur allowlist publikasi agregat. |
| `qr-verification.rotate` | Merotasi token bukti transaksi. |

### Laporan, audit, rekonsiliasi, dan teknis

| Permission | Arti |
|---|---|
| `report.view` / `report.export` | Melihat atau mengekspor laporan dalam scope. |
| `audit.view` | Menelusuri audit log yang telah disanitasi. |
| `reconciliation.view` / `reconciliation.create` | Melihat atau membuat rekonsiliasi. |
| `reconciliation.approve` | Mengesahkan rekonsiliasi; sebaiknya bukan pembuat yang sama. |
| `system.settings.manage` | Mengelola konfigurasi teknis non-secret melalui UI. |
| `system.maintenance` | Maintenance mode dan pemeriksaan sistem. |
| `backup.run` / `backup.view` / `backup.restore` | Menjalankan, melihat, atau memulihkan backup. |
| `audit.retention.execute` | Menjalankan retensi teknis yang disetujui. |

## 4. Matriks role-permission

### 4.1 Akun, nasabah, wilayah, dan master

| Permission | Warga | Petugas | Bendahara | Admin | Superadmin |
|---|:---:|:---:|:---:|:---:|:---:|
| `profile.view`, `profile.update` | O | O | O | O | O |
| `user.view` | — | O* | O* | O* | O* |
| `user.view.area` | — | A | A | — | — |
| `user.view.all` | — | — | — | X | X |
| `user.create`, `user.update`, `user.activate` | — | — | — | X | — |
| `user.verify`, `user.reject` | — | — | — | X | — |
| `user.reset-password`, `session.revoke` | — | — | — | X | X |
| `role.view` | — | — | — | X | X |
| `role.manage` | — | — | — | — | X |
| `backoffice.access` | — | — | — | X | X |
| `customer.view` | O | A | A | X | — |
| `customer.create-assisted` | — | A | — | X | — |
| `customer.update` | O | A terbatas | — | X | — |
| `customer.card.issue`, `customer.qr.rotate` | — | A | — | X | — |
| `region.view` | O | A | A | X | X |
| `region.manage` | — | — | — | X | — |
| `waste.view`, `price.view` | X | X | X | X | X |
| `waste.manage`, `price.manage` | — | — | — | X | — |

### 4.2 Setoran, transaksi, dan saldo

| Permission | Warga | Petugas | Bendahara | Admin | Superadmin |
|---|:---:|:---:|:---:|:---:|:---:|
| `deposit.view` | O | A | — | X | — |
| `deposit.create`, `deposit.update-draft` | — | A | — | X | — |
| `deposit.finalize` | — | A | — | X | — |
| `transaction.correct` | — | — | — | Khusus | — |
| `transaction.reverse` | — | — | — | Khusus | — |
| `ledger.view` | O | A terbatas | A | X | — |
| `ledger.adjust` | — | — | — | Khusus | — |
| `correction.view-customer` | O | A | A | X | — |

`Khusus` berarti permission tidak melekat otomatis hanya karena role `admin`; permission diberikan kepada admin yang ditunjuk dan diaudit. Superadmin teknis tidak memperoleh permission ini secara implisit.

### 4.3 Penjemputan, pencairan, dan sembako

| Permission | Warga | Petugas | Bendahara | Admin | Superadmin |
|---|:---:|:---:|:---:|:---:|:---:|
| `pickup.view` | O | A | — | X | — |
| `pickup.request` | O | A berbantuan | — | X berbantuan | — |
| `pickup.review`, `pickup.schedule` | — | A bila ditunjuk | — | X | — |
| `pickup.execute`, `pickup.complete` | — | A | — | X | — |
| `pickup.cancel` | O sesuai status | A | — | X | — |
| `pickup.capacity.manage` | — | — | — | X | — |
| `withdrawal.request`, `withdrawal.view` | O | A berbantuan | A | X | — |
| `withdrawal.approve` | — | — | — | X | — |
| `withdrawal.pay` | — | A bila ditugaskan | X | A bila ditugaskan | — |
| `withdrawal.cancel` | O sesuai status | — | A | X | — |
| `grocery.package.view` | X | X | X | X | X |
| `grocery.package.manage` | — | — | — | X | — |
| `grocery.request`, `grocery.view` | O | A berbantuan | — | X | — |
| `grocery.approve` | — | — | — | X | — |
| `grocery.prepare` | — | A | — | X | — |
| `grocery.handover` | — | A | — | A bila ditugaskan | — |
| `grocery.cancel` | O sesuai status | A | — | X | — |

### 4.4 Program, laporan, pengawasan, dan teknis

| Permission | Warga | Petugas | Bendahara | Admin | Superadmin |
|---|:---:|:---:|:---:|:---:|:---:|
| `notification.view` | O | O | O | O | O |
| `announcement.view` | X sesuai audiens | X sesuai audiens | X | X | X |
| `announcement.manage`, `announcement.publish` | — | — | — | X | — |
| `mobile-service.view` | X | A | X | X | X |
| `mobile-service.manage` | — | — | — | X | — |
| `mobile-service.operate` | — | A | — | A | — |
| `target.view` | X sesuai visibilitas | X | X | X | X |
| `target.manage`, `target.publish` | — | — | — | X | — |
| `statistics.internal.view` | — | A terbatas | X | X | — |
| `statistics.public.manage` | — | — | — | X | — |
| `qr-verification.rotate` | — | — | — | X | — |
| `report.view` | — | A terbatas | X | X | — |
| `report.export` | — | — | X | X | — |
| `audit.view` | — | — | — | X | X terbatas teknis |
| `reconciliation.view` | — | A sendiri | X | X | — |
| `reconciliation.create` | — | A input | X | X | — |
| `reconciliation.approve` | — | — | X | X | — |
| `system.settings.manage`, `system.maintenance` | — | — | — | — | X |
| `backup.run`, `backup.view`, `backup.restore` | — | — | — | — | X |
| `audit.retention.execute` | — | — | — | — | X |

## 5. Separation of duties

### Pencairan

1. `withdrawal.approve` memutuskan kelayakan dan tidak mengizinkan pembayaran.
2. `withdrawal.pay` hanya berlaku setelah keputusan approve dan penugasan payer.
3. Pengguna yang mengajukan atas nama warga tidak boleh menyetujui pengajuan yang sama.
4. Idealnya approver dan payer berbeda. Jika keterbatasan personel memaksa orang yang sama, sistem memerlukan permission eksplisit untuk kedua tindakan, konfirmasi tambahan, alasan operasional, dan audit berisiko tinggi; hal ini bukan default.

### Sembako

1. `grocery.approve` mencatat cek ketersediaan manual dan keputusan.
2. `grocery.handover` hanya mengizinkan verifikasi penerima, bukti, dan penyelesaian setelah paket siap.
3. Pelaksana pengajuan berbantuan tidak boleh menyetujui pengajuan yang sama.
4. Approver dan handover sebaiknya berbeda; pengecualian mengikuti kontrol tambahan yang sama seperti pencairan.

### Koreksi dan rekonsiliasi

1. Pembuat transaksi final tidak boleh mengoreksi transaksi sendiri tanpa permission khusus dan alasan konflik kepentingan yang diaudit.
2. Admin yang membuat koreksi tidak boleh menjadi satu-satunya pengesah rekonsiliasi yang memuat koreksi tersebut.
3. Superadmin hanya memulihkan aspek teknis; kebutuhan koreksi data setelah restore tetap diputuskan admin operasional berizin.

### 4.1.1 Kontrak scope record pengguna

`user.view` selalu diperlukan sebagai permission dasar. `user.view.area` dan `user.view.all` adalah scope eksplisit, bukan pengganti permission dasar dan bukan alias role.

- **Own fallback:** jika `user.view` tidak ada, atau tidak ada scope eksplisit, hanya pengguna aktif milik aktor sendiri yang terlihat. Detail pengguna lain ditolak sebagai 404 setelah query scope diterapkan.
- **Area:** aktor harus memiliki `StaffProfile` dengan `active_from <= hari ini` (atau null), `active_to >= hari ini` (atau null), `service_area_id` tidak null, area `is_active = true`, dan pivot area→RT ke RT aktif. Scope hanya memasukkan pengguna aktif yang memiliki `CustomerProfile` pada RT aktif tersebut, ditambah aktor sendiri.
- **All:** dengan `user.view` dan `user.view.all`, seluruh pengguna aktif terlihat.
- **Policy/query parity:** `VisibleUsers` adalah sumber predicate; `UserPolicy::view` menggunakannya untuk detail/action sehingga URL atau ID yang dapat ditebak tidak memperluas scope.
- **Tidak ada `assigned`:** scope pengguna pada W1 tidak mendefinisikan ulang atau memberi makna baru pada `assigned`.

## 6. Scope record

| Domain | Warga | Petugas | Bendahara | Admin | Superadmin |
|---|---|---|---|---|---|
| Profil/saldo/transaksi | Milik sendiri | Nasabah pada tugas/area dan data minimum | Pencairan/rekonsiliasi terkait | Seluruh operasional sesuai permission | Tidak ada akses bisnis default |
| Penjemputan | Milik sendiri | Ditugaskan/area | Tidak ada | Seluruh operasional | Tidak ada |
| Pencairan | Milik sendiri | Ditugaskan sebagai payer | Disetujui/siap dibayar | Seluruh sesuai tindakan | Tidak ada |
| Sembako | Milik sendiri | Ditugaskan prepare/handover | Tidak ada | Seluruh sesuai tindakan | Tidak ada |
| Laporan/audit | Tidak ada | Ringkasan tugas sendiri | Keuangan sesuai tugas | Sesuai permission | Teknis dan akses, bukan isi bisnis penuh secara default |

Policy wajib memeriksa kepemilikan atau penugasan pada query dan aksi. ID record yang dapat ditebak tidak boleh memperluas scope.

## 7. Lifecycle permission

1. Role baseline dibuat melalui seeder/migrasi yang ditinjau.
2. Perubahan permission memerlukan aktor berizin, alasan, dan audit nilai lama/baru.
3. Permission sensitif ditinjau berkala dan saat pergantian personel.
4. Penonaktifan akun mencabut sesi dan assignment aktif.
5. Tidak ada akun bersama. Setiap tindakan harus dapat ditautkan ke satu pengguna manusia atau proses sistem teridentifikasi.
6. Uji permission wajib mencakup akses menu, URL langsung, Livewire action, ekspor, file, dan record lintas warga sebagaimana [TEST_PLAN.md](TEST_PLAN.md).

## 8. Referensi

- Perilaku: [REQUIREMENTS.md](REQUIREMENTS.md)
- Invariant: [BUSINESS_RULES.md](BUSINESS_RULES.md)
- Keamanan otorisasi: [SECURITY.md](SECURITY.md)
- SOP per role: [OPERATIONS.md](OPERATIONS.md)
