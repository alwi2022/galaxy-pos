<!-- ===== VIEW 1: barcode_files.blade.php (File-based approach) ===== -->
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Barcode Print</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .barcode-container { 
            width: 30%; 
            float: left; 
            text-align: center; 
            border: 1px solid #000; 
            margin: 5px; 
            padding: 10px;
            box-sizing: border-box;
        }
        .clear { clear: both; }
        .product-name { font-weight: bold; margin-bottom: 5px; }
        .product-price { margin-bottom: 10px; }
        .barcode-image { margin: 10px 0; }
        .product-code { font-size: 12px; margin-top: 5px; }
    </style>
</head>
<body>
    @php $counter = 0; @endphp
    @foreach ($dataproduk as $produk)
        <div class="barcode-container">
            <div class="product-name">{{ $produk->nama_produk }}</div>
            <div class="product-price">Rp. {{ format_uang($produk->harga_jual) }}</div>
            <div class="barcode-image">
                <img src="{{ public_path($produk->barcode_file) }}" 
                     alt="{{ $produk->kode_produk }}" 
                     style="width: 150px; height: 50px;">
            </div>
            <div class="product-code">{{ $produk->kode_produk }}</div>
        </div>
        
        @php $counter++; @endphp
        @if ($counter % 3 == 0)
            <div class="clear"></div>
        @endif
    @endforeach
    
    @if ($counter % 3 != 0)
        <div class="clear"></div>
    @endif
</body>
</html>