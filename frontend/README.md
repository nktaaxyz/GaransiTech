# GaransiTech Frontend

Frontend aplikasi GaransiTech yang dibangun dengan Next.js dan TypeScript.

## Fitur utama

- Dashboard ringkasan klaim garansi
- Manajemen pelanggan, vendor, produk, dan unit produk
- Kelola masa berlaku garansi
- Proses klaim garansi dan update status
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
