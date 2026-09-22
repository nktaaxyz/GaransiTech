# Product Requirements Document (PRD)

## GaransiTech

**Sistem Manajemen Garansi dan Klaim Produk Berbasis Web**  
Versi: 1.0  
Status: Draft implementasi  
Tanggal: 22 September 2026

## 1. Ringkasan Produk

GaransiTech adalah aplikasi web untuk membantu toko peralatan jaringan dan komputer mencatat produk yang memiliki garansi serta mengelola klaim garansi secara terstruktur.

Aplikasi digunakan oleh staf toko melalui satu akun/peran, yaitu **Admin Toko**. Pelanggan dan vendor tidak menggunakan aplikasi secara langsung. Pelanggan menyampaikan keluhan kepada toko melalui komunikasi yang tersedia, kemudian admin mencatat dan memprosesnya di GaransiTech.

GaransiTech bukan aplikasi kasir dan bukan sistem pencatatan seluruh transaksi penjualan. Data hanya dicatat ketika diperlukan untuk registrasi garansi atau penanganan klaim.

## 2. Latar Belakang dan Masalah

- Pencatatan garansi dan klaim masih dilakukan secara manual.
- Data pelanggan, unit produk, dan garansi sulit dicari karena tidak terpusat.
- Admin kesulitan mengetahui garansi yang masih aktif atau mendekati masa berakhir.
- Riwayat penanganan klaim belum terdokumentasi secara konsisten.
- Prioritas penanganan keluhan belum memiliki rekomendasi yang terstruktur.

## 3. Tujuan Produk

1. Memusatkan pencatatan pelanggan, produk, unit produk, garansi, dan klaim.
2. Menampilkan status garansi berdasarkan periode tanggal secara otomatis.
3. Membantu admin mencatat dan memantau proses klaim sampai selesai.
4. Menyimpan riwayat perubahan status dan catatan penanganan klaim.
5. Menyediakan pencarian dan ringkasan untuk membantu pekerjaan admin.
6. Menyediakan rekomendasi kategori dan urgensi keluhan menggunakan AI jika fitur tersebut sudah diaktifkan.

## 4. Pengguna dan Hak Akses

Sistem hanya memiliki satu peran:

### Admin Toko

Admin dapat:

- Login dan logout.
- Mengelola data pelanggan.
- Mengelola data vendor.
- Mengelola data produk dan unit produk.
- Mendaftarkan garansi.
- Mencatat klaim garansi.
- Mengubah status dan menambahkan catatan klaim.
- Melihat riwayat, pencarian, dashboard, dan laporan.

Pelanggan dan vendor tidak memiliki akun serta tidak login ke aplikasi.

## 5. Batasan Ruang Lingkup

### Termasuk

- Autentikasi admin.
- Manajemen pelanggan.
- Manajemen vendor.
- Manajemen produk dan unit produk.
- Registrasi garansi per unit produk.
- Pemantauan status garansi.
- Manajemen klaim dan riwayat statusnya.
- Pencarian data.
- Ringkasan dashboard dan laporan dasar.
- Rekomendasi kategori/urgensi keluhan berbasis AI sebagai bantuan admin.

### Tidak termasuk

- Pencatatan setiap transaksi penjualan.
- Kasir, pembayaran, atau laporan keuangan.
- Portal pelanggan atau vendor.
- Pengajuan klaim mandiri oleh pelanggan.
- Pengiriman dan logistik.
- Diagnosis fisik produk secara otomatis.
- Keputusan klaim otomatis oleh AI.
- Integrasi marketplace, POS, distributor, service center, WhatsApp, atau SMS.
- Aplikasi mobile pada tahap awal.
- Dukungan multi-toko kompleks.

## 6. Konsep Data Bisnis

Sistem mencatat data yang relevan dengan garansi, bukan seluruh penjualan.

### Registrasi garansi

Registrasi garansi dibuat ketika sebuah unit produk perlu dicatat sebagai produk bergaransi. Informasi minimalnya:

- Pelanggan.
- Produk.
- Nomor serial unit.
- Tanggal pembelian atau tanggal mulai garansi.
- Tanggal berakhir garansi.
- Kode garansi.

### Klaim garansi

Klaim dibuat ketika pelanggan menyampaikan kerusakan kepada toko. Klaim harus terhubung ke data garansi yang sesuai dan menyimpan:

- Kode klaim.
- Unit/garansi terkait.
- Deskripsi kerusakan.
- Tanggal klaim.
- Status proses.
- Catatan penanganan.
- Riwayat perubahan status.

## 7. Workflow Utama

### 7.1 Registrasi dan Pemantauan Garansi

```text
Pelanggan membeli produk
        ↓
Admin mencari atau menambahkan pelanggan
        ↓
Admin memilih/mencatat unit produk dan serial number
        ↓
Admin membuat registrasi garansi
        ↓
Sistem membuat kode garansi
        ↓
Sistem menghitung status berdasarkan tanggal
        ↓
Admin dapat mencari dan memantau garansi
```

Status garansi:

- **Aktif**: tanggal hari ini masih berada dalam periode garansi.
- **Akan berakhir**: tanggal berakhir mendekati batas yang ditentukan sistem.
- **Berakhir**: tanggal garansi sudah lewat.

### 7.2 Pengajuan dan Penanganan Klaim

```text
Pelanggan menyampaikan keluhan kepada toko
        ↓
Admin mencari garansi berdasarkan kode, pelanggan, produk, atau serial number
        ↓
Sistem memeriksa keberadaan dan masa berlaku garansi
        ↓
Admin mencatat klaim dan deskripsi kerusakan
        ↓
Sistem memberi rekomendasi kategori dan urgensi (opsional)
        ↓
Admin meninjau/mengoreksi rekomendasi
        ↓
Admin memproses dan memperbarui status klaim
        ↓
Sistem menyimpan riwayat dan catatan penanganan
```

Status klaim:

```text
received → forwarded_to_vendor → processing_by_vendor → completed
                                      └──────────────→ rejected
```

Keputusan akhir tetap berada pada admin.

## 8. Fitur Fungsional

### 8.1 Autentikasi Admin

- Admin dapat login dengan email dan password.
- Password disimpan dalam bentuk hash.
- Backend mengeluarkan token akses setelah login berhasil.
- Halaman dan API data hanya dapat diakses dengan token valid.
- Admin dapat logout dan token aktif dinonaktifkan.

### 8.2 Manajemen Pelanggan

- Melihat daftar pelanggan.
- Menambah, mengubah, dan menghapus pelanggan sesuai aturan relasi data.
- Mencari berdasarkan nama atau nomor telepon.
- Melihat unit produk dan riwayat garansi pelanggan.

Data minimal:

```text
name, phone, address
```

### 8.3 Manajemen Vendor

- Melihat daftar vendor.
- Menambah, mengubah, dan menghapus vendor jika tidak memiliki data terkait.
- Menyimpan kontak vendor.

Data minimal:

```text
name, contact_person, phone, email
```

### 8.4 Manajemen Produk dan Unit Produk

- Admin mencatat jenis produk dan vendor.
- Admin mencatat unit fisik berdasarkan serial number.
- Serial number harus unik.
- Satu produk dapat memiliki banyak unit.
- Unit dapat dikaitkan dengan pelanggan dan tanggal pembelian.

### 8.5 Manajemen Garansi

- Admin membuat garansi berdasarkan unit produk.
- Sistem membuat kode garansi unik.
- Admin menentukan tanggal mulai dan berakhir.
- Sistem menampilkan status garansi otomatis.
- Admin dapat mencari garansi berdasarkan kode, pelanggan, produk, atau serial number.

### 8.6 Manajemen Klaim

- Admin membuat klaim hanya untuk garansi yang ditemukan dan dapat diproses.
- Admin mengisi deskripsi kerusakan dan tanggal klaim.
- Sistem menyimpan status awal klaim.
- Admin dapat memperbarui status, catatan, referensi vendor, dan tanggal penyelesaian.
- Sistem menyimpan setiap perubahan status dalam riwayat.

### 8.7 Klasifikasi AI

- Sistem dapat mengirim deskripsi keluhan untuk mendapatkan rekomendasi.
- Kategori rekomendasi: hardware, software, atau jaringan.
- Tingkat urgensi menjadi rekomendasi, bukan keputusan otomatis.
- Admin dapat mengoreksi hasil AI.
- Klaim tetap dapat diproses jika layanan AI tidak tersedia.

### 8.8 Pencarian, Dashboard, dan Laporan

- Dashboard menampilkan jumlah garansi berdasarkan status.
- Dashboard menampilkan klaim yang masih diproses.
- Admin dapat mencari data lintas modul.
- Laporan menampilkan ringkasan garansi dan klaim berdasarkan periode.

## 9. Aturan Bisnis

1. Hanya admin yang menggunakan aplikasi.
2. Customer dan vendor tidak perlu memiliki akun.
3. Tidak semua transaksi penjualan harus dicatat.
4. Unit produk yang sama tidak boleh memiliki serial number ganda.
5. Kode garansi dan kode klaim harus unik.
6. Klaim harus mengacu pada unit/garansi yang valid.
7. Sistem harus memberi peringatan jika garansi sudah berakhir.
8. Data yang masih menjadi referensi data garansi atau klaim tidak boleh dihapus sembarangan.
9. AI hanya memberi rekomendasi; admin mengambil keputusan akhir.
10. Perubahan status klaim harus meninggalkan catatan riwayat.

## 10. Rancangan Entitas

| Entitas | Fungsi |
|---|---|
| `users` | Akun admin toko |
| `vendors` | Data pemasok/vendor |
| `customers` | Data pelanggan |
| `products` | Jenis produk |
| `product_units` | Unit fisik dan serial number |
| `warranties` | Masa berlaku garansi per unit |
| `warranty_claims` | Pengajuan dan proses klaim |
| `claim_status_logs` | Riwayat perubahan status klaim |
| `activity_logs` | Audit aktivitas admin, bila diaktifkan |

Relasi utama:

```text
vendor 1 ──── * products
product 1 ──── * product_units
customer 1 ──── * product_units
product_unit 1 ──── * warranties
warranty 1 ──── * warranty_claims
warranty_claim 1 ──── * claim_status_logs
```

## 11. Kebutuhan Non-Fungsional

- **Performa**: pencarian normal ditampilkan dalam 2–3 detik.
- **Keamanan**: password di-hash, API dilindungi token, dan produksi menggunakan HTTPS.
- **Usability**: antarmuka sederhana untuk admin non-teknis.
- **Keandalan**: transaksi database menjaga konsistensi relasi.
- **Maintainability**: frontend menggunakan Next.js dan backend menggunakan Laravel dengan struktur standar.
- **Portabilitas**: dapat digunakan melalui browser modern pada Windows, macOS, dan Linux.

## 12. Arsitektur Teknis

| Komponen | Teknologi |
|---|---|
| Frontend | Next.js, React, TypeScript |
| Backend | Laravel, PHP |
| Database pengembangan | SQLite |
| Database target produksi | MySQL/MariaDB |
| Komunikasi | REST API |
| Autentikasi | Laravel Sanctum |

Frontend berkomunikasi dengan backend melalui REST API. Backend menangani autentikasi, validasi, aturan bisnis, dan akses database.

## 13. Tahapan Implementasi

1. Finalisasi alur admin dan model data.
2. Pastikan autentikasi login/logout stabil.
3. Implementasikan CRUD pelanggan, vendor, dan produk.
4. Implementasikan unit produk dan serial number.
5. Implementasikan registrasi serta pemantauan garansi.
6. Implementasikan klaim, status, dan riwayat klaim.
7. Implementasikan pencarian dan dashboard.
8. Implementasikan laporan ringkas.
9. Tambahkan klasifikasi AI sebagai fitur pendukung.
10. Lakukan pengujian integrasi dan perbaikan bug.

## 14. Kriteria Penerimaan Minimum

- Admin dapat login dengan akun valid.
- Admin tidak dapat mengakses data tanpa autentikasi.
- Admin dapat mencatat customer, vendor, produk, dan unit.
- Admin dapat mendaftarkan garansi dengan kode unik.
- Status garansi tampil sesuai tanggal.
- Admin dapat membuat klaim dari garansi yang sesuai.
- Status dan riwayat klaim dapat diperbarui.
- Data klaim tampil di dashboard.
- Sistem tidak membutuhkan pencatatan semua transaksi penjualan.
- Kegagalan AI tidak menghentikan proses klaim.
