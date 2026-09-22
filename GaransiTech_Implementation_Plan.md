# Implementation Plan — GaransiTech
### Sistem Manajemen Garansi dan Klaim Produk Berbasis Web
Diturunkan dari PRD v1.0 (15 September 2026) · Dokumen ini: v1.0 · 22 September 2026

---

## 1. Ringkasan Eksekutif

Dokumen ini menerjemahkan PRD GaransiTech menjadi rencana implementasi teknis yang dapat dieksekusi oleh tim (Project Manager, Backend Developer, Frontend Developer, Tester) selama 6 bulan. Fokus utama: membangun sistem CRUD terstruktur (pelanggan, produk, garansi, klaim) dengan status otomatis berbasis tanggal, serta modul klasifikasi keluhan klaim berbasis AI sebagai rekomendasi bagi admin.

Prinsip implementasi:
- **Database-first** — ERD & migration disepakati sebelum coding fitur, karena hampir semua modul saling berelasi (customer → product_unit → warranty → claim).
- **API contract-first** — endpoint REST disepakati backend–frontend di awal (Minggu 3–4) agar frontend bisa mulai membangun UI dengan mock data sambil backend menyelesaikan logika bisnis.
- **Vertical slice per modul** — tiap modul dikerjakan end-to-end (migration → API → UI → test) agar selalu ada fitur yang bisa didemokan tiap 2 minggu, bukan menumpuk integrasi di akhir.

---

## 2. Arsitektur Teknis & Struktur Proyek

| Komponen | Teknologi | Catatan Implementasi |
|---|---|---|
| Frontend | Next.js (React) | App Router, fetch ke Laravel API via Axios/fetch, state form dengan React Hook Form + Zod untuk validasi client-side |
| Backend | Laravel (PHP) | REST API, Laravel Sanctum untuk autentikasi berbasis token, Form Request untuk validasi server-side |
| Database | MySQL | Migration Laravel sebagai single source of truth skema |
| Komunikasi | REST API via HTTPS | Format response konsisten: `{ success, data, message, errors }` |
| AI Classification | Service terpisah / API call dari Laravel | Lihat Bagian 6 |

---

## 3. Rancangan Basis Data (Detail Kolom)

Berdasarkan Bagian 7 PRD, berikut usulan skema tiap entitas agar bisa langsung dijadikan migration.

**Pengguna (users/admin)**
Menyimpan nama, email (harus unik), password (tersimpan terenkripsi), dan peran.

**Vendor**
Menyimpan nama vendor/pemasok dan informasi kontak.

**Pelanggan (customers)**
Menyimpan nama dan nomor telepon (keduanya wajib diisi), serta alamat.
- Aturan penting: pelanggan tidak boleh dihapus selama masih memiliki garansi berstatus aktif — pengecekan ini dilakukan sebelum proses hapus dijalankan, bukan hanya mengandalkan relasi database.

**Produk**
Menyimpan nama produk, merek, tipe/model, dan vendor terkait.

**Unit Produk (product_units)**
Mencatat unit fisik spesifik yang terjual — nomor seri, produk asal, pelanggan pembeli, dan tanggal pembelian.
- Aturan penting: nomor seri harus unik untuk setiap unit, sesuai kebutuhan fungsional di PRD.

**Garansi (warranties)**
Mencatat kode garansi (dibuat otomatis oleh sistem, disarankan format seperti GRS-[tahun-bulan]-[nomor urut]), tanggal mulai dan berakhir, unit produk terkait, serta status.
- Status dihitung otomatis: "aktif" bila jauh dari tanggal berakhir, "akan berakhir" bila sisa waktu ≤ 30 hari (ambang batas ini bisa disesuaikan kemudian), dan "berakhir" bila sudah lewat tanggal.

**Klaim (claims)**
Mencatat garansi terkait, deskripsi keluhan, hasil rekomendasi AI (kategori dan urgensi beserta tingkat keyakinannya), kategori/urgensi final yang ditetapkan admin (bisa berbeda dari rekomendasi AI), status penanganan, dan catatan penanganan.
- Kategori/urgensi versi AI disimpan terpisah dari versi final admin, sehingga ada jejak "AI merekomendasikan apa, admin memutuskan apa" — penting untuk transparansi dan laporan evaluasi.

**Status Klaim (claim_status)**
Daftar status baku: diajukan, diproses, disetujui, ditolak, selesai. Daftar ini disiapkan di awal sistem, bukan diinput bebas oleh admin.

**Log Aktivitas (activity_log)**
Mencatat setiap aktivitas admin (tambah/ubah/hapus data) secara otomatis untuk keperluan audit dan penelusuran riwayat.

### Relasi kunci
`vendors 1—N products`, `products 1—N product_units`, `customers 1—N product_units`, `product_units 1—N warranties`, `warranties 1—N claims`, `claim_status 1—N claims`, `users 1—N activity_log`.

---

## 4. Rencana Komunikasi Data Antar Modul (Ringkas)

| Modul | Kebutuhan komunikasi data (frontend ↔ backend) |
|---|---|
| Autentikasi | Login, logout, dan pengecekan sesi admin yang sedang aktif |
| Pelanggan | Lihat daftar & detail pelanggan, tambah/ubah/hapus data, lihat riwayat produk & garansi per pelanggan |
| Produk & Unit | Lihat & tambah data produk, lihat & tambah unit produk yang terjual per produk |
| Garansi | Lihat daftar & detail garansi, buat/ubah garansi, cari garansi berdasarkan kode/nama, lihat daftar garansi yang mendekati masa berakhir |
| Klaim | Lihat daftar & detail klaim, catat klaim baru, ubah status penanganan klaim, memicu ulang proses klasifikasi AI bila diperlukan |
| Pemantauan & Pencarian | Pencarian lintas seluruh data, ringkasan untuk dashboard |
| Laporan | Ringkasan garansi dan klaim berdasarkan periode tanggal tertentu |

Seluruh komunikasi data (kecuali proses login) hanya bisa diakses setelah admin login, menggunakan format respons yang konsisten antar modul, dan tervalidasi sesuai tabel Kebutuhan Fungsional pada PRD Bagian 5.

---

## 5. Breakdown Fitur → Task Implementasi

### 5.1 Manajemen Pengguna
Proses login dan logout admin dengan email & password, disertai pesan kesalahan umum bila data tidak sesuai. Halaman dashboard hanya bisa diakses setelah admin login.

### 5.2 Manajemen Data Pelanggan
Fitur tambah/ubah/hapus/lihat data pelanggan dengan validasi nama & telepon wajib diisi. Sistem mencegah penghapusan pelanggan yang masih memiliki garansi aktif. Tersedia tampilan riwayat produk & garansi pada halaman detail tiap pelanggan.

### 5.3 Manajemen Data Produk & Unit
Fitur pencatatan data produk (nama, merek, tipe/model) beserta pendaftaran unit fisik yang terjual, dengan validasi nomor seri tidak boleh duplikat.

### 5.4 Manajemen Garansi
Admin membuat data garansi dengan memilih pelanggan dan unit produk terkait, lalu mengisi tanggal mulai & berakhir. Sistem membuat kode garansi secara otomatis dan menghitung status (aktif/akan berakhir/berakhir) berdasarkan tanggal berjalan. Tersedia pencarian garansi berdasarkan kode, nama pelanggan, atau nama produk.

### 5.5 Manajemen Klaim Garansi (dengan AI)
Admin mencatat klaim baru yang terhubung ke data garansi yang valid dan masih berlaku. Sistem secara otomatis memberikan rekomendasi kategori dan urgensi keluhan menggunakan AI, yang dapat ditinjau dan dikoreksi oleh admin sebelum ditetapkan sebagai keputusan final. Status klaim berjalan mengikuti alur: diajukan → diproses → disetujui/ditolak → selesai, disertai catatan penanganan di tiap tahap.

### 5.6 Pemantauan & Pencarian Lintas Data
Dashboard menampilkan ringkasan kondisi garansi dan klaim secara real-time, termasuk daftar garansi yang mendekati masa berakhir dan daftar klaim yang sedang diproses.

### 5.7 Pelaporan
Halaman laporan menampilkan ringkasan jumlah garansi dan klaim berdasarkan status, dengan filter periode tanggal tertentu, untuk keperluan evaluasi layanan pascajual.

### 5.8 Log Aktivitas
Seluruh aktivitas tambah/ubah/hapus data oleh admin pada modul-modul inti (pelanggan, garansi, klaim) tercatat secara otomatis untuk keperluan audit. Prioritas pengerjaan rendah karena tidak eksplisit diminta sebagai halaman tersendiri di PRD.

---

## 6. Implementasi Klasifikasi AI (Fitur 4.9)

Karena PRD hanya menyebut "menggunakan AI" tanpa menentukan model, berikut usulan yang realistis untuk scope capstone 14 minggu:

1. **Input**: teks `complaint_description` yang diketik admin.
2. **Proses**: panggil model bahasa (mis. via API pihak ketiga atau model klasifikasi sederhana yang dilatih/di-*prompt*) untuk menghasilkan:
   - `category`: hardware / software / jaringan
   - `urgency`: low / medium / high
   - `confidence` (opsional, untuk transparansi ke admin)
3. **Fallback**: jika pemanggilan AI gagal (timeout/error), klaim tetap tersimpan dengan `ai_category = null`, admin mengisi kategori manual — sistem tidak boleh blocking hanya karena AI gagal.
4. **Override**: hasil AI disimpan terpisah dari `final_category`/`final_urgency` sehingga ada jejak audit "AI merekomendasikan X, admin memilih Y" — penting untuk laporan evaluasi PRD 1.2 & 2.2 (AI = rekomendasi, bukan keputusan final).
5. **Non-goal**: tidak melakukan diagnosis kerusakan fisik (sesuai batasan PRD 2.2) — klasifikasi murni dari teks keluhan.

Disarankan modul ini dikerjakan Minggu 11–12 setelah CRUD klaim dasar selesai, agar tim punya waktu bereksperimen tanpa memblokir fitur inti.

---

## 7. Rencana Pengerjaan 6 Bulan (Detail per Minggu)

Rentang 14 minggu pada PRD diperluas menjadi 6 bulan (± 24 minggu) agar tiap modul punya waktu yang lebih longgar untuk pengembangan, pengujian, dan perbaikan — mengurangi risiko penumpukan pekerjaan di akhir proyek seperti yang biasa terjadi pada jadwal yang terlalu padat. Setiap bulan dipecah menjadi 4 minggu dengan target keluaran yang jelas, agar progres bisa dipantau tiap minggu, bukan hanya tiap bulan.

### Bulan 1 — Perencanaan & Fondasi Proyek
- **Minggu 1**: Meninjau ulang & menyepakati kebutuhan fungsional dan non-fungsional bersama seluruh tim (dan stakeholder toko bila memungkinkan); menyusun draf awal ERD berdasarkan Bagian 3 dokumen ini.
- **Minggu 2**: Finalisasi ERD (memastikan seluruh relasi antar entitas benar — vendor→produk→unit→garansi→klaim); menyusun kamus data (nama field, tipe, aturan validasi) untuk tiap entitas.
- **Minggu 3**: Menyepakati rencana komunikasi data antar modul (Bagian 4) sebagai kontrak kerja antara sisi backend dan frontend; menyiapkan lingkungan pengembangan untuk seluruh anggota tim (source control, environment lokal).
- **Minggu 4**: Membangun kerangka tampilan dasar (navigasi, halaman login) di sisi frontend; menyusun kerangka test case awal per modul; menyusun daftar pekerjaan (backlog) untuk Bulan 2.
- **Keluaran akhir bulan**: ERD final & kamus data disepakati, kontrak komunikasi data antar modul disepakati, lingkungan kerja tim siap, kerangka tampilan dasar tersedia.

### Bulan 2 — Modul Pengguna, Pelanggan, Vendor & Produk
- **Minggu 5**: Membangun fitur login & logout admin, termasuk penanganan sesi dan pesan kesalahan umum saat data login tidak sesuai.
- **Minggu 6**: Pengujian modul login; mulai pengelolaan data pelanggan — fitur tambah & lihat data pelanggan (nama, telepon, alamat).
- **Minggu 7**: Melengkapi pengelolaan data pelanggan — fitur ubah & hapus, termasuk validasi nama/telepon wajib diisi dan aturan tidak boleh menghapus pelanggan yang masih punya garansi aktif; mulai pengelolaan data vendor.
- **Minggu 8**: Pengelolaan data produk dan pendaftaran unit produk yang terjual, dengan validasi nomor seri tidak boleh duplikat; pengujian menyeluruh seluruh fitur Bulan 2.
- **Keluaran akhir bulan**: Modul login, pelanggan, vendor, dan produk/unit produk berfungsi penuh dan teruji.

### Bulan 3 — Modul Garansi
- **Minggu 9**: Merancang alur pembuatan data garansi (pemilihan pelanggan & unit produk terkait) dan mekanisme pembuatan kode garansi otomatis.
- **Minggu 10**: Mengimplementasikan perhitungan status garansi otomatis (aktif/akan berakhir/berakhir) berdasarkan tanggal berjalan; membangun halaman pembuatan garansi.
- **Minggu 11**: Membangun fitur pencarian garansi berdasarkan kode, nama pelanggan, atau nama produk; membangun halaman daftar & detail garansi.
- **Minggu 12**: Pengujian skenario perubahan status garansi seiring berjalannya waktu (mis. mendekati tanggal berakhir); perbaikan bug dan waktu cadangan.
- **Keluaran akhir bulan**: Modul garansi lengkap — pembuatan, kode otomatis, status otomatis, dan pencarian — berfungsi dan teruji.

### Bulan 4 — Modul Klaim Garansi & Klasifikasi AI
- **Minggu 13**: Merancang alur pencatatan klaim, termasuk pengecekan bahwa garansi terkait valid dan masih berlaku sebelum klaim bisa dibuat.
- **Minggu 14**: Membangun fitur pencatatan klaim baru yang terhubung ke data garansi; menyiapkan integrasi awal layanan klasifikasi AI.
- **Minggu 15**: Menyelesaikan integrasi klasifikasi AI (kategori hardware/software/jaringan & tingkat urgensi) sebagai rekomendasi; membangun tampilan hasil klasifikasi beserta opsi tinjau/koreksi oleh admin.
- **Minggu 16**: Membangun alur status klaim (diajukan → diproses → disetujui/ditolak → selesai) beserta catatan penanganan; menguji skenario ketika proses klasifikasi AI gagal atau tidak tersedia.
- **Keluaran akhir bulan**: Modul klaim lengkap dengan rekomendasi AI, mekanisme koreksi admin, dan alur status berfungsi dan teruji, termasuk skenario gagal.

### Bulan 5 — Pemantauan, Pencarian Lintas Data & Pelaporan
- **Minggu 17**: Merancang & membangun dashboard ringkasan kondisi garansi dan klaim secara real-time.
- **Minggu 18**: Membangun daftar garansi yang mendekati masa berakhir dan daftar klaim yang sedang diproses pada dashboard.
- **Minggu 19**: Membangun fitur pencarian lintas data (pelanggan, produk, garansi, klaim); mengaktifkan pencatatan log aktivitas admin secara otomatis.
- **Minggu 20**: Membangun halaman laporan dengan filter periode tanggal (ringkasan jumlah garansi & klaim berdasarkan status); pengujian menyeluruh fitur Bulan 5.
- **Keluaran akhir bulan**: Dashboard pemantauan, pencarian lintas data, log aktivitas, dan halaman laporan berfungsi dan teruji.

### Bulan 6 — Pengujian Menyeluruh & Persiapan Rilis
- **Minggu 21**: Regression testing menyeluruh terhadap seluruh modul yang sudah dibangun (Bulan 2–5), mencatat seluruh bug yang ditemukan.
- **Minggu 22**: Verifikasi kebutuhan non-fungsional — kecepatan pencarian (target 2–3 detik), akses melalui HTTPS, enkripsi password, serta konsistensi data yang saling berelasi.
- **Minggu 23**: Pengujian penerimaan pengguna (UAT) bersama stakeholder toko menggunakan skenario mendekati kondisi nyata; perbaikan bug hasil temuan UAT.
- **Minggu 24**: Perbaikan bug tersisa, penyusunan dokumentasi akhir proyek, dan persiapan demo/penyerahan hasil kepada stakeholder.
- **Keluaran akhir bulan**: Sistem GaransiTech siap didemokan/diserahkan, seluruh kebutuhan fungsional & non-fungsional pada PRD terverifikasi.

---

## 8. Strategi Testing (selaras dengan peran Tester di PRD)

- **Unit test backend**: validasi Form Request, logika `WarrantyStatusService` (status berubah sesuai tanggal), state machine status klaim.
- **Integration test API**: skenario penuh — buat pelanggan → unit produk → garansi → klaim → klasifikasi AI → update status.
- **Manual/UAT**: dilakukan bersama stakeholder toko sesuai peran Project Manager di PRD, memakai data mendekati kondisi nyata toko jaringan/komputer.
- **Non-functional check**: uji waktu respons pencarian pada volume data simulasi (misal 1.000+ baris) untuk memverifikasi target 2–3 detik; verifikasi HTTPS di environment staging; cek password ter-hash (bukan plaintext) di database.

---

## 9. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Ketergantungan pada layanan AI eksternal (biaya/latensi/downtime) | Modul klaim terhambat | Desain fallback non-blocking (Bagian 6, poin 3); cache/rate-limit pemanggilan |
| Relasi data kompleks (customer→unit→warranty→claim) memperlambat CRUD awal | Timeline molor di Minggu 7–10 | Selesaikan ERD & seeder lebih awal (Minggu 1–4), gunakan factory untuk data uji |
| Scope creep (mis. notifikasi WA, multi-tenant) | Timeline 6 bulan terlampaui | Rujuk kembali ke Bagian 2.2 PRD (batasan) setiap ada permintaan fitur baru |
| Validasi keunikan (serial number, dsb.) terlewat | Data produksi tidak konsisten | Tambahkan constraint di level migration **dan** validasi di Form Request |

---

## 10. Definition of Done per Modul

Sebuah modul dianggap selesai jika: (1) migration & model tersedia, (2) endpoint API sesuai kontrak Bagian 4 dan tervalidasi, (3) UI Next.js terhubung ke API (bukan mock), (4) minimal satu unit test & satu skenario manual lolos, (5) kebutuhan fungsional terkait di PRD Bagian 5 terpenuhi, (6) tidak ada regresi pada modul sebelumnya.

---

*Dokumen ini disusun sebagai turunan teknis dari GaransiTech PRD v1.0 dan dimaksudkan untuk dipakai sebagai acuan kerja tim selama masa pengerjaan capstone (6 bulan).*
