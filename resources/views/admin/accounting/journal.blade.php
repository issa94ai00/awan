@extends('admin.layout')

@section('title', 'قيود اليومية')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-receipt"></i> قيود اليومية</h1>
    <p>سجل القيود المحاسبية للمدخلات اليومية.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($entries->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الحساب</th>
                        <th>الوصف</th>
                        <th>مدين</th>
                        <th>دائن</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                            <td>{{ $entry->ledgerAccount?->name ?? '-' }}</td>
                            <td>{{ $entry->description }}</td>
                            <td>${{ number_format($entry->debit, 2) }}</td>
                            <td>${{ number_format($entry->credit, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $entries->links() }}
        @else
            <p class="empty-state">لا توجد قيود يومية بعد.</p>
        @endif
    </div>
</div>
@endsection
