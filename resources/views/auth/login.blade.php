<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="auth-title">
        <h2>تسجيل الدخول</h2>
        <p>مرحباً بك، أدخل بياناتك للوصول إلى حسابك</p>
    </div>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="auth-field">
            <label for="email" class="auth-label">البريد الإلكتروني</label>
            <div class="auth-input-group">
                <i class="fas fa-envelope auth-input-icon"></i>
                <x-text-input id="email" class="auth-input" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="أدخل بريدك الإلكتروني" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="auth-error" />
        </div>

        <!-- Password -->
        <div class="auth-field">
            <label for="password" class="auth-label">كلمة المرور</label>
            <div class="auth-input-group">
                <i class="fas fa-lock auth-input-icon"></i>
                <x-text-input id="password" class="auth-input"
                                type="password"
                                name="password"
                                required autocomplete="current-password" placeholder="أدخل كلمة المرور" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="auth-error" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="auth-options">
            <label for="remember_me" class="auth-remember">
                <input id="remember_me" type="checkbox" class="auth-checkbox" name="remember">
                <span class="auth-remember-text">تذكرني</span>
            </label>

            @if (Route::has('password.request'))
                <a class="auth-forgot-link" href="{{ route('password.request') }}">
                    نسيت كلمة المرور؟
                </a>
            @endif
        </div>

        <x-primary-button class="auth-submit-btn">
            <i class="fas fa-sign-in-alt"></i>
            <span>تسجيل الدخول</span>
        </x-primary-button>
    </form>

    <style>
        .auth-field {
            margin-bottom: 1.25rem;
        }

        .auth-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .auth-input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .auth-input-icon {
            position: absolute;
            right: 14px;
            color: var(--accent-blue);
            font-size: 0.95rem;
            pointer-events: none;
            transition: color 0.3s ease;
        }

        .auth-input {
            width: 100%;
            padding: 0.85rem 2.8rem 0.85rem 1rem;
            font-size: 0.95rem;
            font-family: 'Cairo', sans-serif;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            background: #fafbfc;
            color: var(--text-dark);
            transition: all 0.3s ease;
            outline: none;
        }

        .auth-input:focus {
            border-color: var(--accent-blue);
            background: white;
            box-shadow: 0 0 0 4px rgba(0, 90, 156, 0.1);
        }

        .auth-input:focus + .auth-input-icon,
        .auth-input-group:focus-within .auth-input-icon {
            color: var(--accent-blue-light);
        }

        .auth-input::placeholder {
            color: #aaa;
            font-size: 0.85rem;
        }

        .auth-error {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.35rem;
        }

        .auth-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .auth-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .auth-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--accent-blue);
            cursor: pointer;
            border-radius: 4px;
        }

        .auth-remember-text {
            font-size: 0.85rem;
            color: #555;
            user-select: none;
        }

        .auth-forgot-link {
            font-size: 0.85rem;
            color: var(--accent-blue);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .auth-forgot-link:hover {
            color: var(--accent-blue-light);
            text-decoration: underline;
        }

        .auth-submit-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.9rem 1.5rem;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Cairo', sans-serif;
            color: white;
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-blue-light));
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 90, 156, 0.3);
        }

        .auth-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 90, 156, 0.4);
            background: linear-gradient(135deg, var(--accent-blue-light), var(--accent-blue));
        }

        .auth-submit-btn:active {
            transform: translateY(0);
        }
    </style>
</x-guest-layout>
