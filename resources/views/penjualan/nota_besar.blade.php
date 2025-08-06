<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            margin: 20px;
        }
        .header {
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 20px;
        }
        table {
            width: 100%;
        }
        table.data {
            border-collapse: collapse;
            margin-top: 20px;
        }
        table.data th, table.data td {
            border: 1px solid #999;
            padding: 8px;
        }
        table.data th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }
        .company-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 3px;
        }
        .header-info td {
            padding: 4px 8px;
            vertical-align: top;
        }
        .total-section {
            background-color: #f9f9f9;
        }
        .total-highlight {
            background-color: #e8f4f8;
            font-weight: bold;
        }
        .signature-section {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>

    {{-- Header --}}
    <table class="header">
        <tr>
            <td width="60%" style="vertical-align: top;">
                <div class="company-name">{{ strtoupper($setting->nama_perusahaan) }}</div>
                <div class="company-address">{{ $setting->alamat }}</div>
                @if(isset($setting->telepon))
                    <div class="company-address">Telp: {{ $setting->telepon }}</div>
                @endif
                @if(isset($setting->email))
                    <div class="company-address">Email: {{ $setting->email }}</div>
                @endif
            </td>
            <td width="40%" style="vertical-align: top;">
                <table class="header-info">
                    <tr>
                        <td><strong>Tanggal</strong></td>
                        <td>: {{ tanggal_indonesia(date('Y-m-d')) }}</td>
                    </tr>
                    <tr>
                        <td><strong>No. Nota</strong></td>
                        <td>: {{ tambah_nol_didepan($penjualan->id_penjualan, 10) }}</td>
                    </tr>
                    <tr>
                        <td><strong>Kasir</strong></td>
                        <td>: {{ auth()->user()->name }}</td>
                    </tr>
                    <tr>
                        <td><strong>Kode Member</strong></td>
                        <td>: {{ $penjualan->member->kode_member ?? '-' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Tabel Transaksi --}}
    <table class="data">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="15%">Kode</th>
                <th width="35%">Nama Produk</th>
                <th width="12%">Harga Satuan</th>
                <th width="8%">Jumlah</th>
                <th width="10%">Diskon</th>
                <th width="15%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detail as $key => $item)
                <tr>
                    <td class="text-center">{{ $key + 1 }}</td>
                    <td>{{ $item->produk->kode_produk }}</td>
                    <td>{{ $item->produk->nama_produk }}</td>
                    <td class="text-right">{{ format_uang($item->harga_jual) }}</td>
                    <td class="text-center">{{ $item->jumlah }}</td>
                    <td class="text-right">{{ $item->diskon }}%</td>
                    <td class="text-right">{{ format_uang($item->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-section">
                <td colspan="6" class="text-right"><strong>Total Harga</strong></td>
                <td class="text-right"><strong>{{ format_uang($penjualan->total_harga) }}</strong></td>
            </tr>
            <tr class="total-section">
                <td colspan="6" class="text-right"><strong>Diskon</strong></td>
                <td class="text-right"><strong>{{ format_uang($penjualan->diskon) }}</strong></td>
            </tr>
            <tr class="total-highlight">
                <td colspan="6" class="text-right"><strong>Total Bayar</strong></td>
                <td class="text-right"><strong>{{ format_uang($penjualan->bayar) }}</strong></td>
            </tr>
            <tr class="total-section">
                <td colspan="6" class="text-right"><strong>Diterima</strong></td>
                <td class="text-right"><strong>{{ format_uang($penjualan->diterima) }}</strong></td>
            </tr>
            <tr class="total-section">
                <td colspan="6" class="text-right"><strong>Kembali</strong></td>
                <td class="text-right"><strong>{{ format_uang($penjualan->diterima - $penjualan->bayar) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    {{-- Footer --}}
    <table class="footer signature-section">
        <tr>
            <td width="60%" style="vertical-align: top;">
                <div style="margin-bottom: 10px;">
                    <strong>Terima kasih telah berbelanja dan sampai jumpa</strong>
                </div>
                <div style="font-size: 12px; color: #666;">
                    Barang yang sudah dibeli tidak dapat dikembalikan<br>
                    kecuali ada kesalahan dari pihak toko.
                </div>
            </td>
            <td width="40%" class="text-center" style="vertical-align: top;">
                <div style="margin-bottom: 60px;">
                    <strong>Hormat Kami</strong>
                </div>
                <div style="border-top: 1px solid #333; padding-top: 5px; display: inline-block; min-width: 120px;">
                    <strong>{{ auth()->user()->name }}</strong><br>
                    <small>Kasir</small>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>