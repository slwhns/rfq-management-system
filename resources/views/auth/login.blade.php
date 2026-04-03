<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QS Smart Data Center Login</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/auth-login.css') }}">
</head>
<body>
    <main class="auth-shell">
        <section class="auth-left">
            <div class="auth-card">
                <div class="auth-brand-wrap">
                    <div class="auth-brand-logo">
                        <img src="{{ asset('images/system-logo.svg') }}" alt="QS Logo">
                    </div>
                    <p class="auth-brand-title">QS Smart DC</p>
                    <p class="auth-brand-subtitle">Pricing System</p>
                </div>

                <h1 class="auth-title">Sign In</h1>

                @if($errors->any())
                    <div class="auth-error">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="auth-field">
                        <label for="email" class="auth-label">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="auth-input" placeholder="Enter your email" />
                    </div>

                    <div class="auth-field">
                        <label for="password" class="auth-label">Password</label>
                        <div class="auth-password-wrap">
                            <input id="password" name="password" type="password" required class="auth-input" placeholder="Enter your password" />
                            <button type="button" id="password-toggle" class="auth-password-toggle" aria-label="Show password" aria-pressed="false">
                                <svg id="password-icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M12 5c5.44 0 9.88 3.44 11 7-1.12 3.56-5.56 7-11 7S2.12 15.56 1 12c1.12-3.56 5.56-7 11-7Zm0 2C8.14 7 4.86 9.29 3.29 12 4.86 14.71 8.14 17 12 17s7.14-2.29 8.71-5C19.14 9.29 15.86 7 12 7Zm0 2.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z"/>
                                </svg>
                                <svg id="password-icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" style="display:none;">
                                    <path d="m2.81 2.81 18.38 18.38-1.42 1.42-3.05-3.05A13.8 13.8 0 0 1 12 20C6.56 20 2.12 16.56 1 13c.56-1.78 1.97-3.47 3.95-4.79L1.39 4.23l1.42-1.42ZM6.4 9.66C4.97 10.74 3.94 11.92 3.29 13 4.86 15.71 8.14 18 12 18c1.18 0 2.31-.21 3.35-.58l-1.93-1.93a3.5 3.5 0 0 1-4.91-4.91L6.4 9.66ZM12 6c5.44 0 9.88 3.44 11 7-.45 1.42-1.46 2.79-2.9 3.95l-1.43-1.43c.93-.79 1.64-1.67 2.04-2.52C19.14 10.29 15.86 8 12 8c-.8 0-1.57.1-2.3.28L8.12 6.7C9.35 6.24 10.64 6 12 6Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="auth-remember">
                        <input type="checkbox" name="remember" />
                        Remember me
                    </label>

                    <button type="submit" class="auth-submit">Sign In</button>
                </form>
            </div>
        </section>

        <section class="auth-right" aria-hidden="true">
            <div class="auth-right-caption">QS Smart Data Center</div>
        </section>
    </main>
</body>
<script src="{{ asset('js/auth-login.js') }}"></script>
</html>
