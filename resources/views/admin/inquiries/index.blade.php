@extends('admin.layout')

@section('title', 'الاستفسارات')

@section('content')
<div class="page-header">
    <div class="header-content">
        <h1><i class="fas fa-envelope"></i> الاستفسارات</h1>
        <p>إدارة رسائل واستفسارات العملاء</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form action="{{ route('admin.inquiries.index') }}" method="GET" class="search-form">
            <div class="input-group">
                <input type="text" name="search" value="{{ request('search') }}" placeholder="ابحث في الاستفسارات...">
                <button type="submit"><i class="fas fa-search"></i></button>
            </div>
        </form>
        <div class="filter-group">
            <select name="status" onchange="this.form.submit()">
                <option value="">جميع الحالات</option>
                <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>جديد</option>
                <option value="read" {{ request('status') == 'read' ? 'selected' : '' }}>مقروء</option>
                <option value="replied" {{ request('status') == 'replied' ? 'selected' : '' }}>تم الرد</option>
            </select>
        </div>
    </div>

    <div class="card-body">
        @if(isset($inquiries) && $inquiries->count() > 0)
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th width="50">#</th>
                        <th>المرسل</th>
                        <th>الموضوع</th>
                        <th>الرسالة</th>
                        <th width="100">الحالة</th>
                        <th width="120">التاريخ</th>
                        <th width="120">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inquiries as $inquiry)
                    <tr class="{{ $inquiry->status === 'new' ? 'table-warning' : '' }}">
                        <td>{{ $inquiry->id }}</td>
                        <td>
                            <strong>{{ $inquiry->name }}</strong>
                            <br><small class="text-muted">{{ $inquiry->email }}</small>
                            <br><small class="text-muted">{{ $inquiry->phone }}</small>
                        </td>
                        <td>{{ $inquiry->subject }}</td>
                        <td>{{ Str::limit($inquiry->message, 60) }}</td>
                        <td>
                            <span class="badge badge-{{ $inquiry->status === 'new' ? 'danger' : ($inquiry->status === 'read' ? 'warning' : 'success') }}">
                                {{ $inquiry->status === 'new' ? 'جديد' : ($inquiry->status === 'read' ? 'مقروء' : 'تم الرد') }}
                            </span>
                        </td>
                        <td>{{ $inquiry->created_at->format('Y-m-d') }}</td>
                        <td>
                            <div class="btn-group">
                                <a href="{{ route('admin.inquiries.show', $inquiry) }}" class="btn btn-sm btn-info" title="عرض">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form action="{{ route('admin.inquiries.destroy', $inquiry) }}" method="POST" class="d-inline" onsubmit="return confirm('هل أنت متأكد من الحذف؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="حذف">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            @if(method_exists($inquiries, 'links') && $inquiries->hasPages())
            <nav class="pagination-tailwind" aria-label="Pagination">
                <!-- Mobile view -->
                <div class="mobile-pagination">
                    @if($inquiries->onFirstPage())
                    <span class="btn-prev disabled">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </span>
                    @else
                    <a href="{{ $inquiries->previousPageUrl() }}" class="btn-prev">
                        <i class="fas fa-chevron-right"></i>
                        السابق
                    </a>
                    @endif

                    @if($inquiries->hasMorePages())
                    <a href="{{ $inquiries->nextPageUrl() }}" class="btn-next">
                        التالي
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    @else
                    <span class="btn-next disabled">
                        التالي
                        <i class="fas fa-chevron-left"></i>
                    </span>
                    @endif
                </div>

                <!-- Desktop view -->
                <div class="desktop-pagination">
                    <p class="pagination-info">
                        عرض <span>{{ $inquiries->firstItem() }}</span> إلى <span>{{ $inquiries->lastItem() }}</span> من <span>{{ $inquiries->total() }}</span> استفسار
                    </p>

                    <div class="pagination-buttons">
                        <!-- Previous -->
                        @if($inquiries->onFirstPage())
                        <span class="page-btn prev disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                        @else
                        <a href="{{ $inquiries->previousPageUrl() }}" class="page-btn prev">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        @endif

                        <!-- Page Numbers -->
                        @foreach($inquiries->getUrlRange(1, $inquiries->lastPage()) as $page => $url)
                            @if($page == $inquiries->currentPage())
                            <span class="page-btn active">{{ $page }}</span>
                            @else
                            <a href="{{ $url }}" class="page-btn">{{ $page }}</a>
                            @endif
                        @endforeach

                        <!-- Next -->
                        @if($inquiries->hasMorePages())
                        <a href="{{ $inquiries->nextPageUrl() }}" class="page-btn next">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        @else
                        <span class="page-btn next disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                        @endif
                    </div>
                </div>
            </nav>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-envelope-open" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                <p>لا توجد استفسارات حالياً</p>
            </div>
        @endif
    </div>
</div>
@endsection
