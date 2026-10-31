# Product Requirements Document (PRD)

## GaransiTech

**Sistem Manajemen Garansi dan Klaim Produk Berbasis Web**  
Versi: 1.2
Status: Draft implementasi  


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
6. Membantu admin mengisi deskripsi kerusakan melalui transkripsi audio berbahasa Indonesia.
7. Menyediakan bukti penerimaan barang klaim untuk pelanggan dan bukti pengiriman barang klaim ke vendor dalam PDF terpisah.
8. Mencatat dan memantau permintaan servis berbayar untuk perangkat tanpa garansi aktif, termasuk perangkat yang belum terdaftar.

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
- Pembuatan bukti penerimaan barang klaim dalam format PDF yang dapat dicetak.
- Manajemen permintaan servis berbayar untuk unit dengan garansi berakhir atau perangkat yang belum terdaftar.
- Pencarian data.
- Ringkasan dashboard dan laporan dasar.
- Transkripsi audio berbahasa Indonesia menjadi teks untuk deskripsi kerusakan.

### Tidak termasuk

- Pencatatan setiap transaksi penjualan.
- Kasir, pembayaran, atau laporan keuangan.
- Portal pelanggan atau vendor.
- Pengajuan klaim mandiri oleh pelanggan.
- Portal atau akun pelanggan/vendor untuk mengakses dokumen dan status secara mandiri.
- Pengiriman dan logistik.
- Pengiriman PDF otomatis melalui email, WhatsApp, atau SMS.
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

Proses klaim membedakan dua dokumen: saat toko menerima barang dari pelanggan, admin mencatat kondisi dan membuat bukti penerimaan PDF untuk pelanggan. Saat barang dikirim ke vendor, admin mencatat tanggal, jasa pengiriman, dan nomor resi, lalu membuat dokumen PDF terpisah untuk vendor dan arsip toko. Bukti pengiriman adalah dokumen administrasi toko dan tidak menggantikan bukti dari jasa ekspedisi atau konfirmasi penerimaan vendor.

Status klaim:

```text
received → forwarded_to_vendor → processing_by_vendor → completed
                                      └──────────────→ rejected
```

Keputusan akhir tetap berada pada admin.

### 7.3 Permintaan Servis Mandiri / di Luar Garansi

```text
Pelanggan meminta servis kepada toko
        ↓
Admin memeriksa masa garansi unit
        ↓
Jika garansi aktif: ikuti alur klaim garansi
Jika garansi berakhir/tidak ada atau perangkat belum terdaftar:
admin mencatat data pelanggan dan perangkat pada permintaan servis
        ↓
Admin mencatat keluhan, mitra servis (jika ada), dan estimasi biaya
        ↓
Admin memperbarui status berdasarkan persetujuan pelanggan dan proses servis
```

Permintaan servis di luar garansi merupakan proses terpisah dari klaim garansi. Pelanggan tidak memerlukan akun. Jika perangkat belum terdaftar, admin mencatat nama dan nomor telepon pelanggan, nama perangkat, nomor serial bila tersedia, serta keluhan langsung pada permintaan servis; sistem tidak membuat registrasi garansi atau unit palsu. Pelanggan dapat meninjau estimasi biaya sebelum menyetujui servis.

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

### 8.7 Bukti Penerimaan dan Pengiriman Barang Klaim

- Setelah menerima barang fisik yang akan diklaim, admin dapat mencatat tanggal penerimaan dan kondisi/catatan barang.
- Sistem membuat bukti penerimaan barang dari customer dalam format PDF yang dapat diunduh dan dicetak untuk customer.
- Bukti memuat setidaknya nomor klaim, tanggal penerimaan, identitas pelanggan, identitas produk dan serial number, serta catatan penerimaan.
- Saat meneruskan klaim, admin wajib mencatat tanggal pengiriman, nama jasa pengiriman/ekspedisi, dan nomor resi.
- Setelah status klaim diteruskan ke vendor, admin dapat mengunduh bukti pengiriman PDF terpisah dengan salinan untuk vendor dan arsip toko, masing-masing pada halaman tersendiri. Dokumen memuat tanggal pengiriman, vendor tujuan, jasa pengiriman, nomor resi, identitas unit dan klaim, serta foto bukti kondisi barang dengan tata letak yang siap dicetak.
- Foto bukti klaim disimpan privat di server setelah diunggah dan disertakan pada PDF pengiriman vendor. Format foto yang diunggah untuk PDF adalah JPG atau PNG dengan ukuran maksimal 5 MB.
- Pembuatan PDF dengan foto memerlukan ekstensi PHP GD aktif pada server.
- Bukti pengiriman merupakan catatan administrasi toko, bukan konfirmasi independen dari jasa ekspedisi atau vendor.
- Pemberian dokumen dilakukan oleh admin; pengiriman otomatis atau portal akses tidak termasuk.
- Bukti penerimaan tidak menyatakan klaim disetujui, ditolak, atau sudah selesai.

### 8.8 Permintaan Servis Mandiri / di Luar Garansi

- Admin dapat membuat permintaan servis untuk unit dengan garansi berakhir atau tanpa data garansi.
- Admin dapat membuat permintaan servis mandiri untuk perangkat yang belum terdaftar, tanpa membuat data pelanggan, unit, produk, atau garansi palsu.
- Data mandiri mencakup nama dan nomor telepon pelanggan, nama perangkat, nomor serial (opsional), keluhan/kebutuhan servis, mitra servis (opsional), estimasi biaya (opsional), catatan, dan status.
- Sistem menolak permintaan servis berbayar untuk unit yang memiliki garansi aktif; admin diarahkan menggunakan alur klaim garansi.
- Jika perangkat/unit sudah terdaftar tetapi tidak memiliki garansi aktif, permintaan servis dapat dihubungkan ke data unit tersebut.
- Status servis: `requested`, `quoted`, `approved`, `in_progress`, `completed`, atau `cancelled`.
- Admin dapat memperbarui estimasi biaya, mitra servis, catatan, dan status proses.
- Permintaan servis berbayar dicatat terpisah dari klaim garansi dan tidak mengubah riwayat atau status garansi.

### 8.9 Transkripsi suara untuk deskripsi kerusakan

- Admin dapat mendiktekan deskripsi kerusakan dalam bahasa Indonesia.
- Hasil transkripsi mengisi kolom deskripsi kerusakan dan tetap dapat diedit sebelum disimpan.
- Audio diproses oleh model pengenalan suara lokal di server aplikasi dan tidak dikirim ke layanan AI pihak ketiga.
- Admin tetap dapat mengetik atau mengoreksi deskripsi jika transkripsi gagal.

### 8.10 Pencarian, Dashboard, dan Laporan

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
9. Admin harus memeriksa dan dapat mengoreksi hasil transkripsi sebelum menyimpan klaim.
10. Perubahan status klaim harus meninggalkan catatan riwayat.
11. Bukti penerimaan hanya dibuat setelah toko menerima barang fisik untuk klaim dan tidak menggantikan keputusan klaim.
12. Unit dengan garansi aktif tidak dapat dibuatkan permintaan servis berbayar; permintaan tersebut mengikuti alur klaim garansi.
13. Permintaan servis dapat dibuat tanpa registrasi unit atau garansi dengan menyimpan identitas pelanggan dan perangkat pada permintaan itu sendiri.
14. Permintaan servis berbayar harus terpisah dari klaim garansi dan tidak memperpanjang atau mengubah masa garansi.

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
| `paid_service_requests` | Permintaan servis berbayar; dapat terhubung ke unit terdaftar atau menyimpan data perangkat mandiri |
| `activity_logs` | Audit aktivitas admin, bila diaktifkan |

Relasi utama:

```text
vendor 1 ──── * products
product 1 ──── * product_units
customer 1 ──── * product_units
product_unit 1 ──── * warranties
warranty 1 ──── * warranty_claims
warranty_claim 1 ──── * claim_status_logs
product_unit 1 ──── * paid_service_requests (product unit optional per request)
vendor 1 ──── * paid_service_requests (opsional)
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
- Admin dapat mencatat tanggal dan kondisi penerimaan barang, lalu mengunduh PDF bukti penerimaan untuk customer.
- Admin wajib mengisi tanggal pengiriman, jasa pengiriman, dan nomor resi sebelum klaim diteruskan ke vendor.
- Admin dapat mengunggah foto bukti JPG/PNG maksimal 5 MB dan mengunduh PDF pengiriman vendor yang memuat foto serta salinan vendor dan toko pada halaman terpisah.
- Admin dapat mencatat servis untuk unit tanpa garansi aktif atau perangkat belum terdaftar tanpa membuat data unit/garansi palsu.
- Sistem menolak permintaan servis berbayar untuk unit dengan garansi aktif, yang harus diproses melalui alur klaim.
- Admin dapat mengelola estimasi biaya dan memperbarui status servis.
- Data klaim tampil di dashboard.
- Sistem tidak membutuhkan pencatatan semua transaksi penjualan.
- Kegagalan AI tidak menghentikan proses klaim.
