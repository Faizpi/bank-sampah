# Model data cutover

## Identitas

`users.role` non-null empat nilai tetap; `status` akun tetap menunggu verifikasi/aktif/ditolak/nonaktif. Profil Warga dan profil/penugasan pengurus terpisah. `access_role_locks` menyimpan singleton dan jumlah Admin aktif yang dipelihara trigger. Tabel izin/role dinamis serta tabel layanan atas nama Warga tidak ada pada schema akhir.

## Sumber transaksi

| Record | Pemilik | Claimant | Final |
| --- | --- | --- | --- |
| Deposit | customer_id | staff_id | finalized_at + ledger masuk |
| Pickup | customer_id | assigned_staff_id | completed_at + deposit final |
| Withdrawal | customer_id | payer_id | paid_at + receipt_ledger_entry_id |
| Grocery | customer_id | claimed_by_id | handed_over_at + handover_actor_id + receipt_ledger_entry_id |

Claim mencatat waktu; release menghapus claimant aktif dan mencatat riwayat. Field pengecualian menyimpan alasan, pengunci, penyelesai dan hasil. Kolom approval/expiry rutin pencairan/sembako sudah dihapus. Foto privat nullable.

`balance_holds` unik per sumber. `ledger_entries` memiliki boundary sumber/jenis unik dan bersifat append-only. Snapshot nilai/harga tidak mengikuti perubahan master sesudah transaksi.

Migration historis tetap berurutan; migration cutover menjatuhkan dependensi lama sebelum seed baru. Cutover transaksi menolak baseline yang tidak kosong; data nyata tidak boleh dianggap dummy.

Hash finalisasi/completion disimpan pada record transaksi agar hasil retry tetap dikenali setelah key operasional dibersihkan. `cancellation_requested_at` dan alasan dipakai untuk race serah fisik, tanpa menambah enum rutin. `physical_handed_over_at` nullable membedakan waktu fisik yang diketahui dari waktu pencatatan exception.

## Pemeriksaan harian 8 Oktober 2026

Rekonsiliasi baru memakai status `selesai` / `selesai_dengan_selisih`; status historis tetap terbaca. Untuk kompatibilitas schema, `approver_id` dan `approved_at` menyimpan pelaku/waktu pencatatan pemeriksaan baru, bukan persetujuan orang kedua. Snapshot dan catatan selisih tidak membuat mutasi atau menghapus jejak transaksi. Tidak diperlukan reset atau perubahan schema untuk fitur ini.
