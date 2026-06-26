@extends('admin.layout')

@section('title', 'تذاكر الدعم')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-ticket-alt"></i> تذاكر الدعم</h1>
    <p>عرض حالة التذاكر ومسار التفاعلات.</p>
</div>

<div class="card">
    <div class="card-body">
        @if($tickets->count())
            <table class="table">
                <thead>
                    <tr>
                        <th>التذكرة</th>
                        <th>العميل</th>
                        <th>الأولوية</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ $ticket->customer?->name ?? 'غير محدد' }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>{{ ucfirst($ticket->status) }}</td>
                            <td>{{ $ticket->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $tickets->links() }}
        @else
            <p class="empty-state">لا توجد تذاكر حتى الآن.</p>
        @endif
    </div>
</div>
@endsection
