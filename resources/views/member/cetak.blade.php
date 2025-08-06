<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cetak Kartu Member</title>
    <style>
        body {
           font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
            margin: 10px;
            margin: 10px;
        }
        table {
            width: 100%;
        }
        td {
            width: 50%;
            padding: 10px;
            vertical-align: top;
        }

        .card {
            position: relative;
            width: 324px;  /* 85.6mm = 324px at 96dpi */
             height: 204px; /* 53.98mm = 204px at 96dpi */
            border: none;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0 auto;
        }

      
        .card img.bg {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0; left: 0;
            object-fit: cover;
            z-index: 1;
        }
        .logo {
            position: absolute;
            top: 8px;
            right: 8px;
            z-index: 2;
            text-align: right;
            padding: 4px 6px;
            border-radius: 4px;
            backdrop-filter: blur(5px);
        }

   
        .logo img {
            height: 40px;
        }
        .logo p {
            margin: 0;
            font-weight: bold;
        }
        .nama {
            position: absolute;
            bottom: 40px;
            right: 10px;
            font-weight: bold;
            z-index: 2;
        }
        .telepon {
            position: absolute;
            bottom: 20px;
            right: 10px;
            z-index: 2;
        }
        .barcode {
            position: absolute;
            bottom: 15px;
            left: 15px;
            z-index: 3;
            background: rgba(255,255,255,0.95);
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        @media print {
            body {
                background: white;
                margin: 0;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

      
    </style>
</head>
<body>
    <table>
        @foreach ($datamember->chunk(2) as $chunk)
        <tr>
            @foreach ($chunk as $member)
            <td>
                <div class="card">
                    <img class="bg" src="{{ asset($setting->path_kartu_member) }}" alt="bg">

                    <div class="logo">
                        <p>{{ $setting->nama_perusahaan }}</p>
                        <img src="{{ asset($setting->path_logo) }}" alt="logo">
                    </div>

                    <div class="nama">{{ $member->nama }}</div>
                    <div class="telepon">{{ $member->telepon }}</div>

                    <div class="barcode">
                    {!! QrCode::size(80)->generate(url('member/track/' . $member->kode_member)) !!}
                    </div>
                </div>
            </td>
            @endforeach

            @if ($chunk->count() == 1)
                <td></td>
            @endif
        </tr>
        @endforeach
    </table>
</body>
</html>
