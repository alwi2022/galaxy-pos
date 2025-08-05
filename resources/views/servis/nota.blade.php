<!-- resources/views/servis/nota.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Nota Servis</title>
    <style>
        * {
            font-family: monospace;
            font-size: 12px;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="text-center">
        <h3>{{ $servis->cabang->nama_cabang ?? 'TOKO' }}</h3>
        <small>{{ $servis->cabang->alamat ?? '' }}</small><br>
        <div class="divider"></div>
        <p>
            Kode Servis : {{ $servis->kode_servis }} <br>
            Nama        : {{ $servis->nama_pelanggan }} <br>
            Barang      : {{ $servis->tipe_barang }} <br>
            Kerusakan   : {{ $servis->kerusakan }} <br>
            Status      : {{ ucfirst($servis->status) }} <br>
            Tanggal     : {{ tanggal_indonesia($servis->created_at, true) }} <br>
            Biaya       : Rp {{ format_uang($servis->biaya_servis) }} <br>
            Garansi     : {{ $servis->garansi_hari }} hari <br>
            Berlaku s/d : {{ \Carbon\Carbon::parse($servis->tanggal_selesai)->addDays($servis->garansi_hari)->format('d-m-Y') }} <br>
            <p class="text-center">
                {!! QrCode::size(80)->generate(route('servis.track', $servis->kode_servis)) !!}
            </p>
        </p>
        <div class="divider"></div>
        <p>TERIMA KASIH</p>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>
