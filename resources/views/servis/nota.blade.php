<!-- resources/views/servis/nota.blade.php -->
@php
    $jasa = (int) $servis->biaya_servis;
    $sparepartTotal = (int) $servis->sparepart_total;
    $total = $jasa + $sparepartTotal;
    $dibayar = (int) $servis->dibayar;
    $sisa = (int) $servis->sisa;
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Faktur Servis {{ $servis->kode_servis }}</title>
    <style>
        * { font-family: "Courier New", monospace; font-size: 12px; }
        body { margin: 16px; color: #000; }
        .title { text-align: center; }
        .title h3 { margin: 0; }
        .muted { color: #333; }
        table { width: 100%; border-collapse: collapse; }
        .info td { padding: 1px 4px; vertical-align: top; }
        .items th, .items td { border-bottom: 1px dashed #000; padding: 3px 4px; }
        .items th { border-top: 1px solid #000; border-bottom: 1px solid #000; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section { margin-top: 10px; font-weight: bold; background: #eee; padding: 2px 4px; }
        .subtotal td { padding: 3px 4px; font-weight: bold; }
        .total-row td { padding: 4px; border-top: 1px solid #000; font-weight: bold; font-size: 13px; }
        .terbilang { margin-top: 6px; font-style: italic; }
        .ttd { margin-top: 30px; }
        .ttd td { text-align: center; vertical-align: bottom; height: 60px; }
        @media print { .noprint { display: none; } }
    </style>
</head>
<body>
    <div class="title">
        <h3>{{ $servis->cabang->nama_cabang ?? 'TOKO' }}</h3>
        <div class="muted">{{ $servis->cabang->alamat ?? '' }}</div>
        <strong>FAKTUR SERVIS</strong>
    </div>

    <table class="info" style="margin-top:8px">
        <tr>
            <td width="55%">
                No. Faktur : {{ $servis->kode_servis }}<br>
                Tanggal    : {{ tanggal_indonesia($servis->created_at, false) }}<br>
                Status     : {{ ucfirst($servis->status) }}
            </td>
            <td>
                Pelanggan : {{ $servis->nama_pelanggan ?: '-' }}<br>
                Telp/HP   : {{ $servis->telepon ?: '-' }}<br>
                Barang    : {{ $servis->tipe_barang ?: '-' }} {{ $servis->merk }}
            </td>
        </tr>
    </table>

    {{-- A. SERVICE (Jasa) --}}
    <div class="section">A. SERVICE / URAIAN PEKERJAAN</div>
    <table class="items">
        <thead>
            <tr>
                <th width="6%">No</th>
                <th>Uraian Pekerjaan</th>
                <th class="text-right" width="25%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $servis->kerusakan ?: 'Jasa Servis' }}</td>
                <td class="text-right">{{ format_uang($jasa) }}</td>
            </tr>
        </tbody>
        <tr class="subtotal">
            <td colspan="2" class="text-right">Sub Total Service</td>
            <td class="text-right">{{ format_uang($jasa) }}</td>
        </tr>
    </table>

    {{-- B. SPAREPARTS --}}
    <div class="section">B. SPAREPARTS</div>
    <table class="items">
        <thead>
            <tr>
                <th width="6%">No</th>
                <th width="14%">Kode</th>
                <th>Nama Barang</th>
                <th class="text-right" width="8%">Qty</th>
                <th class="text-right" width="16%">Harga</th>
                <th class="text-right" width="18%">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @forelse($servis->spareparts as $i => $sp)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ optional($sp->produk)->kode_produk }}</td>
                    <td>{{ optional($sp->produk)->nama_produk }}</td>
                    <td class="text-right">{{ $sp->jumlah }}</td>
                    <td class="text-right">{{ format_uang($sp->harga_jual) }}</td>
                    <td class="text-right">{{ format_uang($sp->subtotal) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center muted">Tidak ada sparepart</td></tr>
            @endforelse
        </tbody>
        <tr class="subtotal">
            <td colspan="5" class="text-right">Sub Total Sparepart</td>
            <td class="text-right">{{ format_uang($sparepartTotal) }}</td>
        </tr>
    </table>

    {{-- Total --}}
    <table style="margin-top:8px">
        <tr class="total-row">
            <td class="text-right">TOTAL</td>
            <td class="text-right" width="25%">Rp. {{ format_uang($total) }}</td>
        </tr>
        <tr>
            <td class="text-right">Terbayar</td>
            <td class="text-right">Rp. {{ format_uang($dibayar) }}</td>
        </tr>
        <tr>
            <td class="text-right">Sisa</td>
            <td class="text-right">Rp. {{ format_uang($sisa) }}</td>
        </tr>
    </table>

    <div class="terbilang">Terbilang: {{ ucwords(trim(terbilang($total))) }} rupiah</div>

    <div class="muted" style="margin-top:6px">
        Garansi {{ $servis->garansi_hari }} hari
        @if($servis->tanggal_selesai)
            &middot; berlaku s/d {{ \Carbon\Carbon::parse($servis->tanggal_selesai)->addDays($servis->garansi_hari)->format('d-m-Y') }}
        @endif
    </div>

    <table class="ttd">
        <tr>
            <td>__________________<br>Teknisi / Marketing</td>
            <td>__________________<br>Penerima</td>
        </tr>
    </table>

    <div class="text-center" style="margin-top:10px">
        {!! QrCode::size(70)->generate(route('servis.track', $servis->kode_servis)) !!}
        <div class="muted">Scan untuk lacak status servis</div>
    </div>

    <script>window.print();</script>
</body>
</html>
