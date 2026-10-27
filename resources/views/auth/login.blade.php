@extends('layouts.auth')

@section('login')
<style>
    body.login-page {
        background: transparent;
        margin: 0;
        padding: 0;
    }
    
    .login-container {
        display: flex;
        min-height: 100vh;
        height: 100vh;
    }
    
    /* Left side - Illustration */
    .login-left {
        flex: 1;
        background-color: #605CA8;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 60px;
    }
    
    .login-illustration {
        width: 400px;
        height: auto;
        max-width: 90%;
        object-fit: contain;
        border-radius: 20px;
    }
    
    /* Right side - Form */
    .login-right {
        flex: 1;
        background-color: #f8f9fa;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        padding: 60px 40px;
    }

    /* Login box reusing AdminLTE login-box width */
    .login-right .login-box {
        width: 360px;
        max-width: 100%;
        margin: 0;
    }

    /* Login logo (AdminLTE style) */
    .login-right .login-logo {
        margin-bottom: 25px;
        font-size: 30px;
        text-align: center;
    }

    .login-right .login-logo b {
        color: #605CA8;
    }

    .login-right .login-box-msg {
        color: #666;
    }

    /* Tint the AdminLTE primary button to match the illustration */
    .login-right .btn-primary {
        background-color: #605CA8;
        border-color: #564fa0;
    }

    .login-right .btn-primary:hover,
    .login-right .btn-primary:focus,
    .login-right .btn-primary:active {
        background-color: #514c90;
        border-color: #464080;
    }

    /* Responsive design */
    @media (max-width: 1024px) {
        .login-left {
            padding: 40px;
        }
        
        .login-illustration {
            width: 350px;
            padding: 30px;
        }
    }
    
    @media (max-width: 768px) {
        .login-container {
            flex-direction: column;
        }
        
        .login-left {
            flex: 0 0 300px;
            padding: 30px;
        }
        
        .login-illustration {
            width: 250px;
            padding: 20px;
        }
        
        .login-right {
            flex: 1;
            padding: 40px 30px;
        }
    }
    
    @media (max-width: 480px) {
        .login-left {
            flex: 0 0 250px;
            padding: 20px;
        }
        
        .login-illustration {
            width: 200px;
            padding: 15px;
        }
        
        .login-right {
            padding: 30px 20px;
        }
    }
</style>

<div class="login-container">
    <!-- Left side - Illustration -->
    <div class="login-left">
        <img src="{{ asset('img/login-illustration.png') }}" alt="POS Service Illustration" class="login-illustration">
    </div>

    <!-- Right side - Login Form -->
    <div class="login-right">
        <div class="login-box">
            <div class="login-logo">
                @if(!empty($setting->nama_perusahaan))
                    {{ $setting->nama_perusahaan }}
                @else
                    <b>Galaxy</b> Computer
                @endif
            </div>

            <div class="login-box-body">
                <p class="login-box-msg">Silakan login untuk memulai sesi</p>

                <form action="{{ route('login') }}" method="post" class="form-login">
                    @csrf

                    <div class="form-group has-feedback @error('email') has-error @enderror">
                        <input type="email" name="email" class="form-control" placeholder="Email"
                            value="{{ old('email') }}" required autofocus>
                        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                        @error('email')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group has-feedback @error('password') has-error @enderror">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                        <span class="glyphicon glyphicon-lock form-control-feedback"></span>
                        @error('password')
                            <span class="help-block">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-xs-8">
                            <div class="checkbox icheck">
                                <label>
                                    <input type="checkbox" name="remember"> Ingat saya
                                </label>
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <button type="submit" class="btn btn-primary btn-block btn-flat">Login</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection