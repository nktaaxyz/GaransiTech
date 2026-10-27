# GaransiTech Frontend

Frontend aplikasi GaransiTech yang dibangun dengan Next.js dan TypeScript.

## Fitur utama

- Dashboard ringkasan klaim garansi
- Manajemen pelanggan, vendor, produk, dan unit produk
- Kelola masa berlaku garansi
- Proses klaim garansi dan update status
- Pencatatan dan pelacakan servis berbayar untuk unit dengan garansi berakhir
- Lampiran foto bukti kerusakan yang disimpan lokal di browser admin
- UI admin yang responsif untuk desktop dan mobile

## Persiapan

Pastikan Node.js sudah terinstall di komputer Anda.

```bash
npm install
```

## Menjalankan aplikasi

```bash
npm run dev
```

Kemudian buka:

http://localhost:3000

## Build untuk produksi

```bash
npm run build
```

## Struktur utama

```bash
app/
  components/
  customers/
  login/
  products/
  vendors/
  warranties/
  lib/
```

## Catatan

Aplikasi ini menghubungkan ke backend Laravel melalui environment variable `NEXT_PUBLIC_API_URL`.
Jika perlu, buat file `.env.local` dengan contoh:

```bash
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

Foto bukti klaim saat ini hanya disimpan di IndexedDB browser yang digunakan admin. Foto tidak dikirim ke backend, tidak tersinkron antarperangkat, dan dapat hilang jika data browser dihapus. Format yang didukung JPG, PNG, dan WEBP dengan ukuran maksimal 5 MB.

Pada form klaim, rekaman suara klien otomatis dikirim ke endpoint transkripsi lokal backend. Hasil ucapan disimpan bersama poin keluhan pada klaim. Perekaman memerlukan izin mikrofon dan konfigurasi Whisper lokal di backend; poin otomatis saat ini berupa kalimat transkrip yang diformat sebagai daftar, bukan ringkasan AI.
