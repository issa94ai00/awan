@extends('admin.layout')

@section('title', 'إيصالات الاستلام')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-truck-loading"></i> إيصالات الاستلام</h1>
    <a href="{{ route('admin.purchase-receipts.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> إيصال استلام جديد
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>رقم الإيصال</th>
                    <th>المورد</th>
                    <th>أمر الشراء</th>
                    <th>تاريخ الاستلام</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse($receipts as $receipt)
                <tr>
                    <td>{{ $receipt->receipt_number }}</td>
                    <td>{{ $receipt->supplier?->name ?? '-' }}</td>
                    <td>{{ $receipt->purchaseOrder?->order_number ?? '-' }}</td>
                    <td>{{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</td>
                    <td>
                        <a href="{{ route('admin.purchase-receipts.show', $receipt) }}" class="btn btn-sm btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="{{ route('admin.purchase-receipts.edit', $receipt) }}" class="btn btn-sm btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('admin.purchase-receipts.destroy', $receipt) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('هل أنت متأكد؟')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center">لا توجد إيصالات استلام</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
