@extends('admin.layout')

@section('title', 'حركات المخزون')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-exchange-alt"></i> حركات المخزون</h1>
    <p>سجل جميع تحركات المخزون من دخول وخروج وتعديلات.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($movements->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>المنتج</th>
                        <th>النوع</th>
                        <th>الكمية</th>
                        <th>المرجع</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movements as $movement)
                        <tr>
                            <td>{{ $movement->created_at->format('Y-m-d') }}</td>
                            <td>{{ $movement->product?->name_ar ?? 'غير معروف' }}</td>
                            <td>{{ ucfirst($movement->movement_type) }}</td>
                            <td>{{ $movement->quantity }}</td>
                            <td>{{ $movement->reference ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $movements->links() }}
        @else
            <p class="empty-state">لا توجد حركات مخزون حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
