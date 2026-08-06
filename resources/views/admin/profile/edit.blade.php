@extends('admin.layout')

@section('title', 'الملف الشخصي')

@push('styles')
<style>
    .profile-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .profile-header {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 3rem;
        color: white;
    }

    .profile-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 0.25rem;
    }

    .profile-email {
        color: #718096;
        font-size: 0.875rem;
    }

    .profile-form {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .form-section {
        margin-bottom: 2rem;
    }

    .form-section:last-child {
        margin-bottom: 0;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1.25rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
    }

    @media (max-width: 640px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #4a5568;
    }

    .form-label span {
        color: #e53e3e;
    }

    .form-input {
        padding: 0.75rem 1rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .form-input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-input:disabled {
        background: #f7fafc;
        cursor: not-allowed;
    }

    .form-hint {
        font-size: 0.75rem;
        color: #718096;
    }

    .password-section {
        background: #f7fafc;
        border-radius: 8px;
        padding: 1.5rem;
        margin-top: 1.5rem;
    }

    .password-toggle {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        cursor: pointer;
        color: #4a5568;
        font-size: 0.875rem;
    }

    .password-toggle input {
        width: 16px;
        height: 16px;
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e2e8f0;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.875rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
    }

    .error-message {
        color: #e53e3e;
        font-size: 0.75rem;
        margin-top: 0.25rem;
    }
</style>
@endpush

@section('content')
<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="profile-name">{{ $user->name }}</div>
        <div class="profile-email">{{ $user->email }}</div>
    </div>

    <!-- Profile Form -->
    <form class="profile-form" method="POST" action="{{ route('admin.profile.update') }}">
        @csrf
        @method('PUT')

        <!-- Personal Info Section -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-user-circle"></i>
                المعلومات الشخصية
            </h3>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">الاسم <span>*</span></label>
                    <input type="text" name="name" class="form-input" value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني <span>*</span></label>
                    <input type="email" name="email" class="form-input" value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Password Section -->
        <div class="form-section">
            <h3 class="section-title">
                <i class="fas fa-lock"></i>
                تغيير كلمة المرور
            </h3>
            <p class="form-hint">اترك هذه الحقول فارغة إذا كنت لا تريد تغيير كلمة المرور</p>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">كلمة المرور الحالية</label>
                    <input type="password" name="current_password" class="form-input" placeholder="أدخل كلمة المرور الحالية">
                    @error('current_password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" name="password" class="form-input" placeholder="أدخل كلمة المرور الجديدة">
                    @error('password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group full-width">
                    <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" name="password_confirmation" class="form-input" placeholder="أعد إدخال كلمة المرور الجديدة">
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                حفظ التغييرات
            </button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                إلغاء
            </a>
        </div>
    </form>
</div>
@endsection
