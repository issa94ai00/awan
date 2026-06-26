@extends('admin.layout')

@section('title', 'الحضور')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-clock"></i> الحضور</h1>
    <p>سجل حضور الموظفين وتقارير الوقت.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($attendance->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>التاريخ</th>
                        <th>الموظف</th>
                        <th>دخول</th>
                        <th>خروج</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($attendance as $record)
                        <tr>
                            <td>{{ $record->date->format('Y-m-d') }}</td>
                            <td>{{ $record->employee?->first_name }} {{ $record->employee?->last_name }}</td>
                            <td>{{ optional($record->clock_in)->format('H:i') ?? '-' }}</td>
                            <td>{{ optional($record->clock_out)->format('H:i') ?? '-' }}</td>
                            <td>{{ ucfirst($record->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $attendance->links() }}
        @else
            <p class="empty-state">لا توجد سجلات حضور بعد.</p>
        @endif
    </div>
</div>
@endsection
