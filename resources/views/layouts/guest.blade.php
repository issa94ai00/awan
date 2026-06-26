<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.direction', 'rtl') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ get_setting('site_name') ?? '' }} - {{ __('messages.login') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ filemtime(public_path('assets/css/style.css')) }}">
        @if(app()->getLocale() === 'ar')
            <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}?v={{ filemtime(public_path('assets/css/rtl.css')) }}">
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            :root {
                --primary-dark: #1a2634;
                --primary-gray: #2c3e50;
                --accent-blue: #005a9c;
                --accent-blue-light: #0077be;
                --accent-gold: #c9a959;
                --text-light: #f8f9fa;
                --text-dark: #212529;
                --bg-light: #f4f6f9;
                --glass-bg: rgba(255, 255, 255, 0.1);
                --glass-border: rgba(255, 255, 255, 0.2);
            }

            body.auth-body {
                font-family: 'Cairo', sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, var(--primary-dark) 0%, #1a1a2e 50%, var(--accent-blue) 100%);
                background-size: 400% 400%;
                animation: gradientShift 15s ease infinite;
                position: relative;
                overflow: hidden;
            }

            @keyframes gradientShift {
                0% { background-position: 0% 50%; }
                50% { background-position: 100% 50%; }
                100% { background-position: 0% 50%; }
            }

            body.auth-body::before {
                content: '';
                position: absolute;
                top: -50%;
                left: -50%;
                width: 200%;
                height: 200%;
                background: radial-gradient(circle, rgba(201, 169, 89, 0.05) 0%, transparent 50%);
                animation: floatBg 20s ease-in-out infinite;
            }

            @keyframes floatBg {
                0%, 100% { transform: translate(0, 0); }
                25% { transform: translate(5%, -5%); }
                50% { transform: translate(-5%, 5%); }
                75% { transform: translate(5%, 5%); }
            }

            .auth-wrapper {
                width: 100%;
                max-width: 460px;
                padding: 20px;
                position: relative;
                z-index: 1;
            }

            .auth-logo {
                text-align: center;
                margin-bottom: 2rem;
            }

            .auth-logo a {
                display: inline-block;
                transition: transform 0.3s ease;
            }

            .auth-logo a:hover {
                transform: scale(1.05);
            }

            .auth-logo img {
                height: 80px;
                width: auto;
                object-fit: contain;
                filter: brightness(0) invert(1);
            }

            .auth-logo .auth-logo-text {
                color: white;
                font-size: 1.3rem;
                font-weight: 700;
                margin-top: 0.5rem;
                opacity: 0.9;
            }

            .auth-card {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border-radius: 20px;
                padding: 2.5rem 2rem;
                box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
                border: 1px solid rgba(255, 255, 255, 0.3);
                position: relative;
                overflow: hidden;
            }

            .auth-card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 4px;
                background: linear-gradient(90deg, var(--accent-blue), var(--accent-gold), var(--accent-blue));
            }

            .auth-title {
                text-align: center;
                margin-bottom: 1.5rem;
            }

            .auth-title h2 {
                font-size: 1.5rem;
                font-weight: 700;
                color: var(--primary-dark);
                margin-bottom: 0.25rem;
            }

            .auth-title p {
                font-size: 0.9rem;
                color: #666;
            }

            .auth-floating-icons {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
                overflow: hidden;
            }

            .auth-floating-icons i {
                position: absolute;
                color: rgba(255, 255, 255, 0.08);
                font-size: 3rem;
                animation: floatIcon 6s ease-in-out infinite;
            }

            .auth-floating-icons i:nth-child(1) { top: 10%; right: 5%; animation-delay: 0s; }
            .auth-floating-icons i:nth-child(2) { top: 60%; right: 85%; animation-delay: 1.5s; font-size: 2.5rem; }
            .auth-floating-icons i:nth-child(3) { bottom: 15%; right: 10%; animation-delay: 3s; font-size: 2rem; }
            .auth-floating-icons i:nth-child(4) { top: 30%; right: 90%; animation-delay: 4.5s; font-size: 2.8rem; }

            @keyframes floatIcon {
                0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.08; }
                50% { transform: translateY(-15px) rotate(10deg); opacity: 0.15; }
            }

            @media (max-width: 480px) {
                .auth-card {
                    padding: 2rem 1.5rem;
                    border-radius: 16px;
                }

                .auth-logo img {
                    height: 60px;
                }

                .auth-title h2 {
                    font-size: 1.3rem;
                }
            }
        </style>
    </head>
    <body class="auth-body">
        <div class="auth-floating-icons">
            <i class="fas fa-trowel"></i>
            <i class="fas fa-wrench"></i>
            <i class="fas fa-paint-roller"></i>
            <i class="fas fa-hard-hat"></i>
        </div>

        <div class="auth-wrapper">
            <div class="auth-logo">
                <a href="{{ url('/') }}">
                    <img src="{{ get_setting('site_logo') ? (str_starts_with(get_setting('site_logo'), 'assets/') ? asset(get_setting('site_logo')) : asset('storage/' . get_setting('site_logo'))) : asset('assets/images/logo.png') }}" alt="{{ get_setting('site_name') ?? '' }}">
                </a>
                <div class="auth-logo-text">{{ get_setting('site_name') ?? '' }}</div>
            </div>

            <div class="auth-card">
                <!-- Language Switcher -->
                <div class="language-switcher" style="text-align: center; margin-bottom: 1rem;">
                    <a href="{{ route('lang.switch', 'en') }}" class="lang-btn {{ app()->getLocale() === 'en' ? 'active' : '' }}" style="padding: 5px 10px; margin: 0 5px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; font-size: 14px;">
                        🇬🇧 English
                    </a>
                    <a href="{{ route('lang.switch', 'ar') }}" class="lang-btn {{ app()->getLocale() === 'ar' ? 'active' : '' }}" style="padding: 5px 10px; margin: 0 5px; border: 1px solid #ddd; border-radius: 5px; text-decoration: none; color: #333; font-size: 14px;">
                        🇸🇦 العربية
                    </a>
                </div>

                {{ $slot }}
            </div>
        </div>
    </body>
</html>
