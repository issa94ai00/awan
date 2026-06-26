@extends('admin.layout')

@section('title', 'الفواتير')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-invoice"></i> الفواتير</h1>
    <p>عرض وتصفية فواتير المبيعات.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($invoices->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>رقم الفاتورة</th>
                        <th>العميل</th>
                        <th>المجموع</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->customer_name ?? 'عميل عام' }}</td>
                            <td>${{ number_format($invoice->total, 2) }}</td>
                            <td>{{ ucfirst($invoice->status) }}</td>
                            <td>{{ $invoice->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $invoices->links() }}
        @else
            <p class="empty-state">لا توجد فواتير حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
