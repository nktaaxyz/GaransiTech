<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin: 18mm;
        }

        body {
            color: #17233b;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            line-height: 1.35;
        }

        .document {
            page-break-inside: avoid;
        }

        .header {
            border-bottom: 2px solid #244c86;
            margin-bottom: 5mm;
            padding-bottom: 4mm;
        }

        .eyebrow {
            color: #5273a1;
            font-size: 8pt;
            font-weight: bold;
            letter-spacing: 1px;
            margin: 0 0 2mm;
            text-transform: uppercase;
        }

        h1 {
            color: #17345f;
            font-size: 17pt;
            line-height: 1.2;
            margin: 0 0 2mm;
        }

        .claim-code {
            color: #52627a;
            font-size: 9pt;
            margin: 0;
        }

        .copy-label {
            background: #eaf1fb;
            border: 1px solid #c9d8ee;
            border-radius: 3px;
            color: #244c86;
            display: inline-block;
            font-size: 8pt;
            font-weight: bold;
            margin: 0 0 4mm;
            padding: 2mm 3mm;
        }

        .section-title {
            color: #244c86;
            font-size: 9pt;
            font-weight: bold;
            margin: 0 0 2mm;
        }

        .details {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .details th,
        .details td {
            border: 1px solid #d5deea;
            padding: 2mm 2.5mm;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        .details th {
            background: #f1f5fa;
            color: #41536e;
            font-size: 8pt;
            font-weight: bold;
            width: 34%;
        }

        .details td {
            color: #1d2c43;
        }

        .photo-section {
            margin-top: 4mm;
        }

        .photo-frame {
            border: 1px solid #d5deea;
            height: 72mm;
            padding: 1mm;
            text-align: center;
            width: 100%;
        }

        .photo-frame img {
            display: block;
            height: 72mm;
            width: 100%;
        }

        .notice {
            background: #f7f9fc;
            border-left: 3px solid #5273a1;
            color: #4a5a70;
            font-size: 8pt;
            margin-top: 4mm;
            padding: 3mm;
        }

        .signatures {
            border-collapse: collapse;
            margin-top: 7mm;
            table-layout: fixed;
            width: 100%;
        }

        .signatures td {
            color: #41536e;
            padding: 0 5mm;
            text-align: center;
            width: 50%;
        }

        .signature-line {
            border-top: 1px solid #718096;
            margin-top: 16mm;
            padding-top: 2mm;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@foreach ($copies as $copy)
    <div class="document">
        <header class="header">
            <p class="eyebrow">GaransiTech · Dokumen Pengiriman</p>
            <h1>Bukti Pengiriman Klaim ke Vendor</h1>
            <p class="claim-code">Nomor klaim: <strong>{{ $claim->claim_code }}</strong></p>
        </header>

        <div class="copy-label">SALINAN: {{ strtoupper($copy) }}</div>

        <p class="section-title">Informasi pengiriman dan perangkat</p>
        <table class="details">
            <colgroup>
                <col style="width: 34%">
                <col style="width: 66%">
            </colgroup>
            <tr>
                <th>Tanggal dikirim toko</th>
                <td>{{ $claim->forwarded_date?->format('d-m-Y') ?? '-' }}</td>
            </tr>
            <tr>
                <th>Jasa pengiriman</th>
                <td>{{ $claim->shipping_carrier ?: '-' }}</td>
            </tr>
            <tr>
                <th>Nomor resi</th>
                <td>{{ $claim->vendor_reference_number }}</td>
            </tr>
            <tr>
                <th>Vendor tujuan</th>
                <td>{{ $claim->vendor?->name ?? '-' }}{{ $claim->vendor?->phone ? ' — '.$claim->vendor->phone : '' }}</td>
            </tr>
            <tr>
                <th>Customer</th>
                <td>{{ $claim->customer?->name ?? '-' }}{{ $claim->customer?->phone ? ' — '.$claim->customer->phone : '' }}</td>
            </tr>
            <tr>
                <th>Produk / nomor serial</th>
                <td>{{ $claim->product?->name ?? '-' }} / {{ $claim->serial_number ?: '-' }}</td>
            </tr>
            <tr>
                <th>Keluhan / kerusakan</th>
                <td>{{ $claim->damage_description }}</td>
            </tr>
            @if ($claim->received_condition)
                <tr>
                    <th>Kondisi saat diterima toko</th>
                    <td>{{ $claim->received_condition }}</td>
                </tr>
            @endif
        </table>

        <section class="photo-section">
            <p class="section-title">Foto bukti kondisi barang</p>
            <div class="photo-frame">
                <img src="{{ $photoDataUri }}" alt="Foto bukti klaim {{ $claim->claim_code }}">
            </div>
        </section>

        <div class="notice">
            Dokumen ini adalah catatan administrasi toko mengenai pengiriman barang klaim. Jasa pengiriman dan nomor resi dicatat oleh toko, bukan konfirmasi penerimaan dari ekspedisi/vendor dan bukan keputusan klaim.
        </div>

        <table class="signatures">
            <tr>
                <td><div class="signature-line">Pengirim, Toko</div></td>
                <td><div class="signature-line">Penerima, Vendor</div></td>
            </tr>
        </table>
    </div>

    @if (! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
