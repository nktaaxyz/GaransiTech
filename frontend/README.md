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

Foto bukti klaim diunggah ke backend dan dapat disertakan dalam PDF pengiriman vendor. Format yang didukung JPG dan PNG dengan ukuran maksimal 5 MB. Pratinjau foto juga disimpan di IndexedDB browser admin; pratinjau lokal tidak tersinkron antarperangkat.

Detail klaim menyediakan pencatatan barang diterima dan unduhan PDF tanda terima. Setelah klaim diteruskan ke vendor, admin dapat mengunduh PDF pengiriman yang memuat foto bukti dari backend. Tombol cetak detail menggunakan dialog cetak browser.

Permintaan servis berbayar dapat dibuat untuk unit terdaftar yang tidak memiliki garansi aktif maupun perangkat yang belum terdaftar. Untuk perangkat manual, nama dan nomor telepon pelanggan serta nama produk perlu dicatat.

Pada form klaim, rekaman suara klien otomatis dikirim ke endpoint transkripsi lokal backend, lalu transkrip dirangkum menjadi poin keluhan oleh Gemini. Transkrip dan poin tetap dapat ditinjau serta diedit sebelum disimpan. Perekaman memerlukan izin mikrofon dan konfigurasi Whisper lokal; rangkuman AI memerlukan `GEMINI_API_KEY` pada `backend/.env`. Jika Gemini tidak tersedia, poin sederhana dari transkrip tetap diisikan dan dapat diedit manual.
