@extends('admin.layout')

@section('title', 'طلبات الإجازة')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-calendar-day"></i> طلبات الإجازة</h1>
    <p>مراجعة وإدارة طلبات الإجازة المقدمة من الموظفين.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($leaves->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>الموظف</th>
                        <th>نوع الإجازة</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaves as $leave)
                        <tr>
                            <td>{{ $leave->employee?->first_name }} {{ $leave->employee?->last_name }}</td>
                            <td>{{ ucfirst($leave->leave_type) }}</td>
                            <td>{{ $leave->start_date->format('Y-m-d') }}</td>
                            <td>{{ $leave->end_date->format('Y-m-d') }}</td>
                            <td>{{ ucfirst($leave->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $leaves->links() }}
        @else
            <p class="empty-state">لا توجد طلبات إجازة بعد.</p>
        @endif
    </div>
</div>
@endsection
