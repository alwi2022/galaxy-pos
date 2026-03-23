<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Laporan Ringkasan Kas</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #222;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        .table th,
        .table td {
            border: 1px solid #d2d6de;
            padding: 6px 8px;
        }
        .table thead th {
            background: #f4f4f4;
        }
        .total-row td {
            font-weight: bold;
            background: #fafafa;
        }
    </style>
</head>
<body>
    <h3 class="text-center">Laporan Ringkasan Kas</h3>
    <h4 class="text-center">
        Tanggal {{ tanggal_indonesia($awal, false) }}
        s/d
        Tanggal {{ tanggal_indonesia($akhir, false) }}
    </h4>

    <table class="table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Tanggal</th>
                <th>Penjualan Masuk</th>
                <th>Pembelian Keluar</th>
                <th>Pendapatan Lain</th>
                <th>Servis</th>
                <th>Biaya</th>
                <th>Pendapatan Bersih</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr class="{{ blank($row['DT_RowIndex']) ? 'total-row' : '' }}">
                    <td>{{ $row['DT_RowIndex'] }}</td>
                    <td>{{ $row['tanggal'] }}</td>
                    <td class="text-right">{{ $row['penjualan_masuk'] }}</td>
                    <td class="text-right">{{ $row['pembelian_keluar'] }}</td>
                    <td class="text-right">{{ $row['pendapatan_lain'] }}</td>
                    <td class="text-right">{{ $row['servis'] }}</td>
                    <td class="text-right">{{ $row['biaya'] }}</td>
                    <td class="text-right">{{ $row['pendapatan_bersih'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
