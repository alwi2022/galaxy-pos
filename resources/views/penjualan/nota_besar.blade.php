@php
    $pelanggan = $penjualan->member;
    $diskonNominal = hitung_diskon_nominal($penjualan->total_harga, $penjualan->diskon);
    $adaDiskonItem = $detail->contains(fn ($item) => $item->diskon > 0);
    $caraBayar = label_cara_bayar_penjualan($penjualan);
    $ringkasan = array_filter([
        'Sub Total' => $penjualan->total_harga,
        'Diskon ' . $penjualan->diskon . '%' => $diskonNominal ?: null,
        'PPN ' . ($penjualan->ppn_persen + 0) . '%' => $penjualan->ppn_nominal ?: null,
        'Total' => $penjualan->bayar,
        'Dibayar' => $penjualan->dibayar,
        'Sisa' => $penjualan->sisa > 0 ? $penjualan->sisa : null,
    ], fn ($nilai) => $nilai !== null);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota {{ tambah_nol_didepan($penjualan->id_penjualan, 10) }}</title>
    <style>
        @page { margin: 18pt 20pt; }
        body { font-family: Courier, monospace; font-size: 9pt; color: #000; margin: 0; }
        table { width: 569pt; border-collapse: collapse; }
        td, th { padding: 2pt 4pt; vertical-align: top; }
        .kop-nama { font-size: 15pt; font-weight: bold; letter-spacing: 1pt; }
        .kop-alamat { font-size: 8pt; }
        .judul { font-size: 11pt; font-weight: bold; letter-spacing: 2pt; text-align: center; padding: 4pt 0; }
        .info td { padding: 0 4pt; }
        .info .label { width: 70pt; white-space: nowrap; }
        .info .nilai { width: 215pt; }
        .info .lebar { width: 214pt; }
        .ttd { width: 100%; }
        .customer { font-size: 11pt; font-weight: bold; }
        .barang { margin-top: 4pt; border: 1pt solid #000; }
        .barang th { border: 1pt solid #000; font-weight: bold; text-align: center; }
        .barang td { border-left: 1pt solid #000; border-right: 1pt solid #000; }
        .barang tbody tr:last-child td { padding-bottom: 6pt; }
        .ringkas { margin-top: -1pt; page-break-inside: avoid; }
        .ringkas td { border: 1pt solid #000; }
        .terbilang { font-weight: bold; vertical-align: middle; }
        .r { text-align: right; }
        .c { text-align: center; }
        .kotak { border: 1pt solid #000; padding: 4pt 6pt; }
        .ttd td { text-align: center; padding-top: 2pt; }
        .garis { border-top: 1pt solid #000; display: inline-block; min-width: 110pt; padding-top: 2pt; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="padding:0">
                <div class="kop-nama">{{ strtoupper($setting->nama_perusahaan) }}</div>
                <div class="kop-alamat">{{ strtoupper($setting->alamat) }}</div>
                @if (filled($setting->telepon))<div class="kop-alamat">{{ $setting->telepon }}</div>@endif
            </td>
        </tr>
    </table>
    <div class="judul">NOTA PENJUALAN</div>
    <table class="info">
        <tr>
            <td class="label">No.Nota</td><td class="nilai">: {{ tambah_nol_didepan($penjualan->id_penjualan, 10) }}</td>
            <td class="label">Customer</td><td class="customer lebar">: {{ strtoupper($pelanggan->nama ?? 'UMUM') }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal</td><td class="nilai">: {{ \Carbon\Carbon::parse($tanggal)->format('d/m/Y') }}</td>
            <td class="label">Telp</td><td>: {{ $pelanggan->telepon ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Cara Bayar</td><td class="nilai">: {{ $caraBayar }}</td>
            <td class="label">Alamat</td><td>: {{ $pelanggan->alamat ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Kasir</td><td class="nilai">: {{ strtoupper($penjualan->user->name ?? '-') }}</td>
            @if ($penjualan->sisa > 0 && $penjualan->jatuh_tempo)
            <td class="label">Jatuh Tempo</td><td>: {{ $penjualan->jatuh_tempo->format('d/m/Y') }}</td>
            @else
            <td></td><td></td>
            @endif
        </tr>
    </table>

    <table class="barang">
        <thead>
            <tr>
                <th style="width:26pt">No.</th>
                <th style="width:{{ $adaDiskonItem ? 236 : 285 }}pt">NAMA BARANG</th>
                <th style="width:40pt">QTY</th>
                <th style="width:80pt">HARGA</th>
                @if ($adaDiskonItem)<th style="width:40pt">DISC</th>@endif
                <th style="width:90pt">JUMLAH</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($detail as $item)
            <tr>
                <td class="r">{{ $loop->iteration }}</td>
                <td>{{ $item->produk->nama_produk ?? '-' }}</td>
                <td class="r">{{ format_uang($item->jumlah) }}</td>
                <td class="r">{{ format_uang($item->harga_jual) }}</td>
                @if ($adaDiskonItem)<td class="r">{{ $item->diskon > 0 ? $item->diskon . '%' : '-' }}</td>@endif
                <td class="r">{{ format_uang($item->subtotal) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{-- Ringkasan dipisah agar tidak terpotong ke halaman berikutnya --}}
    <table class="ringkas">
        @foreach ($ringkasan as $label => $nilai)
        <tr>
            @if ($loop->first)
            <td style="width:{{ $adaDiskonItem ? 270 : 319 }}pt" rowspan="{{ count($ringkasan) }}" class="terbilang"># {{ strtoupper(trim(terbilang($penjualan->bayar))) }} RUPIAH #</td>
            @endif
            <td style="width:{{ $adaDiskonItem ? 176 : 128 }}pt; @if (in_array($label, ['Total', 'Sisa'])) font-weight:bold @endif">{{ $label }}@if ($label === 'Sub Total') ({{ format_uang($detail->sum('jumlah')) }} item)@endif</td>
            <td style="width:90pt; @if (in_array($label, ['Total', 'Sisa'])) font-weight:bold @endif" class="r">{{ format_uang($nilai) }}</td>
        </tr>
        @endforeach
    </table>

    <table style="margin-top:4pt">
        <tr><td></td><td class="r" style="width:200pt">{{ tanggal_indonesia($tanggal, false) }}</td></tr>
    </table>
    <table style="margin-top:2pt">
        <tr>
            <td style="width:46%; padding:0">
                <div class="kotak c">Barang yang sudah dibeli tidak bisa<br>ditukar / dikembalikan</div>
                @if ($rekening->isNotEmpty())
                <div class="kotak" style="margin-top:4pt">
                    Pembayaran sisa tagihan via transfer:
                    @foreach ($rekening as $bank)
                    <br>{{ $bank->bank_name }} No.Rek {{ $bank->account_number }}@if (filled($bank->account_holder)) a/n {{ $bank->account_holder }}@endif
                    @endforeach
                </div>
                @endif
            </td>
            <td style="padding:0">
                <table class="ttd">
                    <tr><td>Kasir</td><td>Penerima</td></tr>
                    <tr><td style="height:42pt"></td><td></td></tr>
                    <tr>
                        <td><span class="garis">{{ $penjualan->user->name ?? '' }}</span></td>
                        <td><span class="garis">{{ $pelanggan->nama ?? '' }}</span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
