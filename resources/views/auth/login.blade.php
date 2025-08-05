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
    
    /* Main title */
    .login-title {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 8px;
        text-align: center;
        color: #333;
        line-height: 1.2;
    }
    
    .login-subtitle {
        font-weight: 400;
        color: #666;
    }
    
    .login-email {
        font-size: 14px;
        color: #666;
        margin-bottom: 40px;
        text-align: center;
    }
    
    /* Form container */
    .login-form {
        width: 100%;
        max-width: 380px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 8px;
        color: #333;
        text-align: left;
    }
    
    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        background-color: #fff;
        color: #333;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #605CA8;
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }
    
    .form-control::placeholder {
        color: #aaa;
    }
    
    /* Clear link */
    .clear-link {
        text-align: right;
        margin: 20px 0;
    }
    
    .clear-link a {
        color: #605CA8;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s ease;
    }
    
    .clear-link a:hover {
        color: #7c3aed;
        text-decoration: underline;
    }
    
    /* Login button */
    .btn-login {
        width: 100%;
        background: linear-gradient(135deg, #605CA8 0%, #7c3aed 100%);
        color: white;
        padding: 14px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(139, 92, 246, 0.3);
    }
    
    .btn-login:hover {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
    }
    
    .btn-login:active {
        transform: translateY(0);
    }
    
    /* Website link */
    .website-link {
        text-align: center;
        color: #666;
        font-size: 14px;
    }
    
    /* Error styling */
    .has-error .form-control {
        border-color: #ef4444;
    }
    
    .help-block {
        color: #ef4444;
        font-size: 12px;
        margin-top: 5px;
    }
    
    /* Hide original Bootstrap elements */
    .form-control-feedback,
    .checkbox {
        display: none;
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
        
        .login-title {
            font-size: 24px;
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
        
        .login-title {
            font-size: 22px;
        }
        
        .login-form {
            max-width: 100%;
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
        <h1 class="login-title">
            POS SERVIS <span class="login-subtitle">Galaxy Computer</span>
        </h1>
        
       

        <form action="{{ route('login') }}" method="post" class="form-login login-form">
            @csrf
            
            <div class="form-group @error('email') has-error @enderror">
                <label for="email" class="form-label">Email</label>
                <input 
                    type="email" 
                    id="email"
                    name="email" 
                    class="form-control" 
                    placeholder="Enter your email" 
                    required 
                    value="{{ old('email') }}" 
                    autofocus
                >
                @error('email')
                    <span class="help-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="form-group @error('password') has-error @enderror">
                <label for="password" class="form-label">Password</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    class="form-control" 
                    placeholder="Enter your password" 
                    required
                >
                @error('password')
                    <span class="help-block">{{ $message }}</span>
                @enderror
            </div>

            <div class="clear-link">
                <a href="#" onclick="clearForm(); return false;">Clear</a>
            </div>

            <button type="submit" class="btn-login">LOGIN</button>

            <div class="website-link">
                imambahrialwi21@gmail.com
            </div>

            <!-- Hidden elements for compatibility -->
            <div style="display: none;">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="remember"> Remember Me
                    </label>
                </div>
                <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
                <span class="glyphicon glyphicon-lock form-control-feedback"></span>
            </div>
        </form>
    </div>
</div>

<script>
function clearForm() {
    document.getElementById('email').value = '';
    document.getElementById('password').value = '';
    document.getElementById('email').focus();
    
    // Remove error classes if any
    document.querySelectorAll('.has-error').forEach(function(element) {
        element.classList.remove('has-error');
    });
}

// Enhanced form validation display
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.form-login');
    if (form) {
        // Add custom validation styling
        form.addEventListener('submit', function(e) {
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            // Reset previous error states
            document.querySelectorAll('.form-group').forEach(function(group) {
                group.classList.remove('has-error');
            });
            
            let hasError = false;
            
            if (!email.value.trim()) {
                email.closest('.form-group').classList.add('has-error');
                hasError = true;
            }
            
            if (!password.value.trim()) {
                password.closest('.form-group').classList.add('has-error');
                hasError = true;
            }
            
            if (hasError) {
                e.preventDefault();
            }
        });
    }
});
</script>
@endsection