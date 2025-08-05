<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Cek Status Servis')</title>
    <link rel="stylesheet" href="{{ asset('AdminLTE-2.4.18/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body {
            padding: 30px;
            background: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .box {
            border: 1px solid #ddd;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
        }
    </style>
</head>
<body>

    <div class="container">
        @yield('content')
    </div>

</body>
</html>
