@extends('admin.layout')

@section('title', 'ميزان المراجعة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-balance-scale-left"></i> ميزان المراجعة</h1>
    <p>مقارنة إجمالي الخصم مع إجمالي الدائن لضمان توازن السجلات.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($accounts->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الحساب</th>
                        <th>النوع</th>
                        <th>الرصيد</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td>{{ $account->name }}</td>
                            <td>{{ ucfirst($account->type) }}</td>
                            <td>${{ number_format($account->balance, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="trial-summary">
                <p><strong>إجمالي المدين:</strong> ${{ number_format($totalDebit, 2) }}</p>
                <p><strong>إجمالي الدائن:</strong> ${{ number_format($totalCredit, 2) }}</p>
            </div>
        @else
            <p class="empty-state">لا توجد حسابات في ميزان المراجعة.</p>
        @endif
    </div>
</div>
@endsection
