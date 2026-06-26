@extends('admin.layout')

@section('title', 'دفتر الأستاذ')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-book-open"></i> دفتر الأستاذ</h1>
    <p>عرض حسابات دفتر الأستاذ وموازنات الأرصدة.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($accounts->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الكود</th>
                        <th>اسم الحساب</th>
                        <th>النوع</th>
                        <th>الرصيد</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td>{{ $account->code }}</td>
                            <td>{{ $account->name }}</td>
                            <td>{{ ucfirst($account->type) }}</td>
                            <td>${{ number_format($account->balance, 2) }}</td>
                            <td>{{ $account->is_active ? 'نشط' : 'غير نشط' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $accounts->links() }}
        @else
            <p class="empty-state">لا توجد حسابات بعد.</p>
        @endif
    </div>
</div>
@endsection
