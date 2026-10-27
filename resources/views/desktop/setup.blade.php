@extends('layouts.auth')
@section('login')
<div class="login-box">
    <div class="login-logo"><b>Galaxy</b> POS</div>
    <div class="login-box-body">
        <p class="login-box-msg">Pengaturan Awal &mdash; isi nama toko dan buat akun administrator.</p>
        @if($errors->any())
            <div class="alert alert-danger" role="alert">
                <ul style="margin:0;padding-left:18px;">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif
        <form method="post" action="{{ route('desktop.setup.store') }}" class="form-setup">
            @csrf
            <div class="form-group">
                <label for="store-name">Nama Toko</label>
                <input id="store-name" class="form-control" name="store_name" value="{{ old('store_name') }}" required maxlength="255" autofocus>
            </div>
            <div class="form-group">
                <label for="admin-name">Nama Administrator</label>
                <input id="admin-name" class="form-control" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name">
            </div>
            <div class="form-group">
                <label for="admin-email">Email Login</label>
                <input id="admin-email" class="form-control" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="admin-password">Password</label>
                <input id="admin-password" class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="admin-confirm">Ulangi Password</label>
                <input id="admin-confirm" class="form-control" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-primary btn-block btn-flat" type="submit">Simpan dan Buka Toko</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.querySelector('.form-setup');
        if (!form) return;
        form.addEventListener('submit', function () {
            var button = form.querySelector('button[type="submit"]');
            if (button) {
                button.disabled = true;
                button.textContent = 'Menyimpan...';
            }
        });
    });
</script>
@endsection
