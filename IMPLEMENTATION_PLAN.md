# Implementation Plan

## GaransiTech

Dokumen ini menerjemahkan [PRD](./PRD.md) menjadi rencana implementasi teknis yang dapat dikerjakan bertahap.

**Versi:** 1.0  
**Status:** In progress

## Progress Tracker

- [x] Tahap 0 — Finalisasi aturan bisnis.
- [x] Tahap 1 — Stabilitas autentikasi admin (backend).
- [x] Tahap 2 — Penyelarasan database dan model.
- [x] Tahap 3A — API Customer (backend).
- [x] Tahap 3B — API Vendor (backend).
- [x] Tahap 3C — API Product (backend).
- [x] Tahap 3D — API Product Unit (backend).
- [ ] Tahap 4 — Registrasi dan pemantauan garansi.
- [ ] Tahap 5 — Klaim dan riwayat penanganan lengkap.
- [ ] Tahap 6 — Dashboard, pencarian, dan laporan.
- [ ] Tahap 7 — Klasifikasi AI sebagai rekomendasi.
- [ ] Tahap 8 — Audit aktivitas dan keamanan lanjutan.
- [ ] Tahap 9 — Pengujian integrasi dan rilis.

### Task aktif

**Task berikutnya:** Tahap 4 — Registrasi dan pemantauan garansi.

**Aturan pengerjaan:** satu modul backend diselesaikan dan diuji sebelum pindah ke modul berikutnya. Frontend dikerjakan setelah API backend untuk modul terkait stabil.

## 1. Prinsip Implementasi

- Sistem hanya memiliki satu jenis pengguna, yaitu Admin Toko.
- Aplikasi mencatat data yang diperlukan untuk garansi dan klaim, bukan seluruh transaksi penjualan.
- Backend Laravel menjadi sumber kebenaran untuk validasi, autentikasi, aturan bisnis, dan akses database.
- Frontend Next.js hanya menangani tampilan, interaksi form, dan konsumsi REST API.
- Perubahan database dilakukan menggunakan migration Laravel.
- Setiap tahap harus diuji sebelum masuk ke tahap berikutnya.
- AI bersifat opsional dan hanya memberikan rekomendasi; keputusan tetap berada pada admin.

## 2. Kondisi Awal

### Sudah tersedia

- Laravel backend dan Next.js frontend.
- Laravel Sanctum untuk token API.
- Database SQLite untuk pengembangan.
- Tabel users, vendors, customers, products, product_units, warranties, warranty_claims, dan claim_status_logs.
- API login, register, logout, user, dan claims.
- Frontend login/register dan dashboard awal.
- Konfigurasi CORS frontend-backend.

### Perlu dilanjutkan

- Menyelaraskan skema database dengan alur garansi pada PRD.
- Menyelesaikan model dan relasi Eloquent.
- Membuat API CRUD data master.
- Membuat API registrasi garansi dan pemantauan status.
- Menghubungkan klaim dengan data garansi/unit produk.
- Membuat halaman admin untuk seluruh modul.
- Menambahkan pencarian, laporan, audit log, dan klasifikasi AI.

## 3. Target Arsitektur

```text
Next.js frontend
        │ REST API + Bearer token
        ▼
Laravel backend
        │ Eloquent + validation + business rules
        ▼
SQLite (development) / MySQL or MariaDB (production)
```

Struktur fitur frontend:

```text
/login
/
  /customers
  /vendors
  /products
  /product-units
  /warranties
  /claims
  /reports
```

## 4. Tahapan Implementasi

## Tahap 0 — Finalisasi aturan bisnis ✅

**Tujuan:** memastikan implementasi mengikuti kebutuhan mitra dan PRD.

Pekerjaan:

- Konfirmasi bahwa hanya Admin Toko yang login.
- Konfirmasi bahwa transaksi penjualan umum tidak dicatat.
- Tentukan ambang “akan berakhir”, misalnya 30 hari sebelum tanggal berakhir.
- Tentukan apakah satu unit dapat memiliki lebih dari satu periode garansi.
- Tentukan status klaim final dan aturan klaim berulang.

Output:

- Aturan bisnis final.
- Daftar status dan transisi status yang disepakati.

## Tahap 1 — Stabilitas autentikasi admin ✅ (backend)

**Tujuan:** memastikan semua halaman data terlindungi.

Backend:

- Pertahankan endpoint login, logout, dan user.
- Validasi email dan password.
- Pastikan password selalu di-hash.
- Lindungi endpoint data dengan middleware `auth:sanctum`.
- Samakan format error validasi JSON.
- Putuskan kebijakan register publik sesuai keputusan stakeholder.

Frontend:

- Sediakan form login.
- Simpan token dengan aman sesuai keputusan implementasi.
- Redirect user tanpa token ke `/login`.
- Hapus token jika API mengembalikan `401`.
- Sediakan logout.
- Buat helper API terpusat untuk header `Authorization`.

Endpoint:

```text
POST /api/auth/login
POST /api/auth/logout
GET  /api/user
```

Kriteria selesai:

- Admin dapat login dengan akun valid.
- Password salah menghasilkan pesan yang aman.
- Endpoint terlindungi menolak request tanpa token.
- Logout menonaktifkan token aktif.

## Tahap 2 — Penyelarasan database dan model ✅

**Tujuan:** membuat model data mendukung alur registrasi garansi tanpa mencatat semua transaksi.

Pekerjaan:

- Audit migration yang sudah ada.
- Lengkapi `$fillable`, casts, dan relasi pada semua model.
- Hubungkan `warranty_claims` ke `warranties` atau `product_units` sesuai keputusan skema final.
- Tambahkan deskripsi kerusakan pada klaim bila dibutuhkan.
- Samakan penamaan status `forwarded_to_vendor`.
- Samakan nama kolom catatan penyelesaian.
- Buat migration baru; jangan mengedit migration yang sudah berjalan pada lingkungan bersama.
- Tambahkan foreign key dan index untuk kolom pencarian.
- Tambahkan unique constraint untuk serial number, kode garansi, dan kode klaim.

Relasi target:

```text
Vendor 1 ──── * Product
Product 1 ──── * ProductUnit
Customer 1 ──── * ProductUnit
ProductUnit 1 ──── * Warranty
Warranty 1 ──── * WarrantyClaim
WarrantyClaim 1 ──── * ClaimStatusLog
```

Kriteria selesai:

- `php artisan migrate:fresh --seed` berhasil pada database development.
- Relasi dapat digunakan tanpa query manual.
- Data referensi tidak dapat dihapus jika melanggar aturan bisnis.

## Tahap 3 — API data master ✅

**Tujuan:** menyediakan data yang dibutuhkan sebelum registrasi garansi dibuat.

Modul:

- [x] Customers (backend)
- [x] Vendors (backend)
- [x] Products (backend)
- [x] Product units (backend)

### Task 3A — Customer API (selesai)

- [x] Buat `CustomerController`.
- [x] Tambahkan route CRUD customer yang dilindungi Sanctum.
- [x] Tambahkan validasi nama dan nomor telepon.
- [x] Tambahkan pencarian nama/nomor telepon dan pagination.
- [x] Tampilkan jumlah unit produk pada daftar/detail.
- [x] Tampilkan unit produk dan garansi pada detail customer.
- [x] Tolak penghapusan customer yang masih memiliki unit/klaim.
- [x] Tambahkan feature test CRUD, search, validasi, authorization, dan aturan hapus.

### Task 3B — Vendor API (selesai)

- [x] Lengkapi relasi dan `$fillable` model Vendor.
- [x] Buat `VendorController`.
- [x] Tambahkan endpoint CRUD vendor terproteksi Sanctum.
- [x] Tambahkan pencarian nama, contact person, dan email.
- [x] Tambahkan pagination dan `products_count`.
- [x] Tolak penghapusan vendor yang masih memiliki produk.
- [x] Tambahkan feature test CRUD, search, validasi, authorization, dan aturan hapus.

### Task 3C — Product API (selesai)

- [x] Lengkapi model Product dan relasi vendor/product units.
- [x] Buat `ProductController`.
- [x] Tambahkan endpoint CRUD product terproteksi Sanctum.
- [x] Validasi vendor yang dipilih harus tersedia.
- [x] Tambahkan pencarian nama/kategori dan pagination.
- [x] Tolak penghapusan product yang masih memiliki unit.
- [x] Tambahkan feature test.

### Task 3D — Product Unit API (selesai)

- [x] Lengkapi model ProductUnit dan relasi customer/product/warranty.
- [x] Buat `ProductUnitController`.
- [x] Tambahkan endpoint CRUD product unit terproteksi Sanctum.
- [x] Validasi serial number unik.
- [x] Validasi product dan customer yang dipilih harus tersedia.
- [x] Tambahkan pencarian serial number, customer, dan product.
- [x] Tolak penghapusan unit yang sudah memiliki garansi atau klaim.
- [x] Tambahkan feature test.

Endpoint target:

```text
GET    /api/customers
POST   /api/customers
GET    /api/customers/{customer}
PATCH  /api/customers/{customer}
DELETE /api/customers/{customer}

GET    /api/vendors
POST   /api/vendors
GET    /api/vendors/{vendor}
PATCH  /api/vendors/{vendor}
DELETE /api/vendors/{vendor}

GET    /api/products
POST   /api/products
GET    /api/products/{product}
PATCH  /api/products/{product}
DELETE /api/products/{product}

GET    /api/product-units
POST   /api/product-units
GET    /api/product-units/{productUnit}
PATCH  /api/product-units/{productUnit}
DELETE /api/product-units/{productUnit}
```

Validasi minimum:

- Nama customer, vendor, dan produk wajib diisi.
- Serial number wajib unik.
- Relasi product, vendor, dan customer harus valid.
- Penghapusan data yang masih digunakan ditolak dengan pesan yang jelas.
- Semua endpoint membutuhkan autentikasi admin.

Frontend:

- Halaman daftar dengan pencarian.
- Form tambah dan edit.
- Konfirmasi sebelum hapus.
- Empty state, loading state, dan error state.

## Tahap 4 — Registrasi dan pemantauan garansi 🟡

**Tujuan:** mencatat hanya unit yang perlu digaransikan dan memantau masa berlakunya.

Backend:

- [x] Endpoint CRUD warranties.
- [x] Generate `warranty_code` unik di server.
- [x] Validasi tanggal mulai dan tanggal berakhir.
- [x] Hitung status secara dinamis dari tanggal saat ini.
- [x] Sediakan filter `active`, `expiring`, dan `expired`.
- [x] Sediakan pencarian kode garansi, customer, produk, dan serial number.

Endpoint target:

```text
GET    /api/warranties
POST   /api/warranties
GET    /api/warranties/{warranty}
PATCH  /api/warranties/{warranty}
DELETE /api/warranties/{warranty}
```

Frontend:

- [x] Daftar garansi.
- [x] Form registrasi garansi.
- [ ] Detail garansi dan unit produk.
- [x] Badge status garansi.
- [x] Filter garansi yang akan berakhir.

Kriteria selesai:

- Admin dapat membuat garansi tanpa mencatat transaksi penjualan umum.
- Kode garansi selalu unik.
- Status aktif/akan berakhir/berakhir sesuai tanggal.
- Garansi tidak dapat dibuat untuk unit yang tidak valid.

## Tahap 5 — Klaim dan riwayat penanganan 🟡

**Tujuan:** mendukung proses klaim dari pencatatan sampai selesai.

Backend:

- [x] Buat klaim berdasarkan garansi yang dipilih.
- [x] Verifikasi garansi ditemukan dan masih berlaku.
- [x] Generate kode klaim unik.
- [x] Simpan deskripsi kerusakan.
- [x] Sediakan status klaim dan transisi yang valid.
- [x] Simpan setiap perubahan pada `claim_status_logs`.
- [x] Gunakan transaction database saat mengubah klaim dan log.

Endpoint target:

```text
GET    /api/claims
POST   /api/claims
GET    /api/claims/{claim}
PATCH  /api/claims/{claim}
PATCH  /api/claims/{claim}/status
```

Status target:

```text
received
forwarded_to_vendor
processing_by_vendor
completed
rejected
```

Frontend:

- Daftar klaim dengan filter status.
- Form klaim berdasarkan kode garansi atau serial number.
- Detail klaim.
- Timeline riwayat status.
- Form update status dan catatan.
- Tampilan klaim yang sedang diproses.

Kriteria selesai:

- Klaim tidak dapat dibuat dari garansi yang tidak ditemukan atau sudah berakhir.
- Setiap perubahan status memiliki waktu, admin, dan catatan.
- Status final tidak dapat diubah tanpa aturan yang disepakati.

## Tahap 6 — Dashboard, pencarian, dan laporan 🟡

**Tujuan:** membantu admin memantau kondisi layanan garansi.

Backend:

- [x] Endpoint ringkasan dashboard.
- [x] Query agregasi jumlah garansi per status.
- [x] Query agregasi klaim per status dan periode.
- [x] Pencarian pada data yang sering dipakai.

Endpoint target:

```text
GET /api/dashboard/summary
GET /api/reports/warranties
GET /api/reports/claims
```

Frontend:

- Kartu ringkasan garansi aktif, akan berakhir, dan berakhir.
- Kartu klaim diterima, diproses, selesai, dan ditolak.
- Daftar klaim yang masih diproses.
- Filter periode laporan.
- Pencarian dengan debounce dan pagination.

## Tahap 7 — Klasifikasi AI sebagai rekomendasi ⏳

**Tujuan:** membantu admin mengelompokkan keluhan tanpa mengambil keputusan otomatis.

Pekerjaan:

- Definisikan format input deskripsi kerusakan.
- Definisikan output kategori: `hardware`, `software`, `network`.
- Definisikan output urgensi.
- Simpan hasil AI dan hasil koreksi admin secara terpisah.
- Tampilkan indikator bahwa hasil AI adalah rekomendasi.
- Tangani timeout, error, dan layanan AI tidak tersedia.
- Pastikan klaim tetap dapat dibuat tanpa AI.

Kriteria selesai:

- Hasil AI tidak langsung mengubah keputusan klaim.
- Admin dapat mengoreksi rekomendasi.
- Kegagalan AI tidak memblokir workflow klaim.

## Tahap 8 — Audit aktivitas dan keamanan 🟡

**Tujuan:** menyediakan penelusuran aktivitas admin dan memperkuat keamanan.

Pekerjaan:

- [x] Tambahkan `activity_logs`.
- [x] Catat aktivitas create, update, delete, login, logout, dan perubahan status.
- [x] Tambahkan rate limiting untuk autentikasi.
- [x] Pastikan data sensitif tidak masuk log aplikasi.
- [x] Validasi authorization pada setiap endpoint.
- [ ] Gunakan HTTPS pada lingkungan produksi.
- [x] Review CORS dan environment variables sebelum deployment.

## Tahap 9 — Pengujian dan rilis 🟡

### Backend

- [x] Feature test login, logout, dan endpoint terlindungi.
- [x] Feature test CRUD data master.
- [x] Feature test validasi serial number dan kode unik.
- [x] Feature test status masa garansi.
- [x] Feature test pembuatan dan perubahan klaim.
- [x] Test relasi dan aturan penghapusan.
- [x] Integration test alur customer sampai claim selesai.
- [x] Verifikasi `migrate:fresh --seed` pada environment testing.

### Frontend

- Lint dan TypeScript check.
- Build production.
- Uji form validasi.
- Uji redirect token dan `401`.
- Uji loading, empty, success, dan error state.

### End-to-end

```text
Login admin
  → tambah customer
  → tambah vendor
  → tambah produk
  → tambah unit
  → registrasi garansi
  → buat klaim
  → ubah status
  → lihat riwayat
  → logout
```

Perintah validasi minimum:

```text
cd backend
php artisan test

cd frontend
npm run lint
npm run build
```

## 5. Urutan Prioritas

| Prioritas | Modul | Alasan |
|---|---|---|
| P0 | Login, token, proteksi API | Fondasi semua modul |
| P0 | Customers, vendors, products, product units | Data prasyarat garansi |
| P0 | Warranties | Inti registrasi garansi |
| P0 | Claims dan status logs | Inti proses layanan |
| P1 | Dashboard dan pencarian | Pemantauan operasional |
| P1 | Laporan | Evaluasi layanan |
| P2 | Activity logs | Audit tambahan |
| P2 | AI classification | Fitur bantuan, bukan blocker |

## 6. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Skema database belum final | Data perlu dimigrasikan ulang | Finalisasi relasi sebelum data produksi |
| Kode status tidak konsisten | Filter dan laporan salah | Gunakan konstanta/status terpusat |
| Data serial number ganda | Klaim salah sasaran | Unique constraint dan validasi API |
| Garansi kedaluwarsa masih diklaim | Kerugian operasional | Validasi tanggal di backend |
| AI gagal atau lambat | Proses klaim terhambat | AI optional dengan fallback manual |
| Penghapusan data berelasi | Data histori hilang | Restrict delete dan konfirmasi admin |
| Token bocor | Akses tidak sah | HTTPS, expiry/revocation, dan review storage |

## 7. Definition of Done

Sebuah modul dianggap selesai jika:

- Endpoint backend memiliki validasi dan authorization.
- Migration dan model sudah konsisten.
- Frontend memiliki loading, empty, success, dan error state.
- Test terkait berhasil.
- Tidak ada error lint/type/build.
- Alur utama dapat diuji dari UI.
- Dokumentasi endpoint atau perilaku penting diperbarui.

Implementasi dianggap siap untuk demo jika seluruh kriteria penerimaan minimum pada PRD terpenuhi dan alur end-to-end berjalan tanpa pencatatan transaksi penjualan umum.
