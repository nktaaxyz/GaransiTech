<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 36px;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        h1 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        h2 {
            margin: 24px 0 8px;
            font-size: 13px;
        }

        .subtitle,
        .muted {
            color: #4b5563;
        }

        .copy-label {
            border: 1px solid #9ca3af;
            display: inline-block;
            margin: 12px 0 18px;
            padding: 5px 9px;
            font-weight: bold;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            width: 30%;
        }

        .notice {
            border: 1px solid #9ca3af;
            margin-top: 20px;
            padding: 10px;
        }

        .signatures {
            margin-top: 48px;
            width: 100%;
        }

        .signatures td {
            border: 0;
            padding: 0 12px;
            text-align: center;
            width: 50%;
        }

        .signature-line {
            border-top: 1px solid #6b7280;
            margin-top: 60px;
            padding-top: 6px;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <h1>Bukti Penerimaan Barang dari Customer</h1>
    <div class="subtitle">Nomor klaim: {{ $claim->claim_code }}</div>
    <div class="copy-label">Salinan untuk Customer</div>

    <table>
        <tr>
            <th>Tanggal klaim</th>
            <td>{{ $claim->claim_date?->format('d-m-Y') ?? '-' }}</td>
        </tr>
        <tr>
            <th>Tanggal barang diterima toko</th>
            <td>{{ $claim->received_at?->format('d-m-Y') ?? '-' }}</td>
        </tr>
        <tr>
            <th>Customer</th>
            <td>{{ $claim->customer?->name ?? '-' }}{{ $claim->customer?->phone ? ' — ' . $claim->customer->phone : '' }}</td>
        </tr>
        <tr>
            <th>Produk</th>
            <td>{{ $claim->product?->name ?? '-' }}</td>
        </tr>
        <tr>
            <th>Nomor serial</th>
            <td>{{ $claim->serial_number ?: '-' }}</td>
        </tr>
        <tr>
            <th>Keluhan / kerusakan</th>
            <td>{{ $claim->damage_description }}</td>
        </tr>
        <tr>
            <th>Kondisi barang saat diterima</th>
            <td>{{ $claim->received_condition }}</td>
        </tr>
        @if ($claim->receipt_note)
            <tr>
                <th>Catatan penerimaan</th>
                <td>{{ $claim->receipt_note }}</td>
            </tr>
        @endif
    </table>

    <div class="notice">
        Dokumen ini merupakan bukti bahwa toko telah menerima barang untuk proses klaim. Dokumen ini bukan keputusan persetujuan atau penolakan klaim, dan bukan hasil pemeriksaan vendor.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="signature-line">Penerima, Toko</div>
            </td>
            <td>
                <div class="signature-line">Penyerah, Customer</div>
            </td>
        </tr>
    </table>

</body>
</html>
