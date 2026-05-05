@extends('admin.layout')

@section('title', 'إحصائيات الزوار')

@push('styles')
<style>
    /* Stats Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
    .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
    .stat-icon.orange { background: linear-gradient(135deg, #fc4a1a 0%, #f7b733 100%); color: white; }
    .stat-icon.purple { background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); color: white; }
    .stat-icon.red { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        color: #718096;
        font-size: 0.875rem;
    }

    /* Charts Section */
    .charts-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    @media (max-width: 1200px) {
        .charts-grid {
            grid-template-columns: 1fr;
        }
    }

    .chart-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    .chart-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
        margin-bottom: 1rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .chart-container {
        position: relative;
        height: 300px;
    }

    /* Filter Section */
    .filter-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        margin-bottom: 1.5rem;
    }

    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: #4a5568;
    }

    .form-input, .form-select {
        padding: 0.625rem 0.875rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.875rem;
        transition: all 0.2s;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .btn {
        padding: 0.625rem 1.25rem;
        border-radius: 8px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
    }

    /* Table Section */
    .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .table-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2d3748;
    }

    .data-table {
        width: 100%;
        border-collapse: collapse;
    }

    .data-table th {
        background: #f7fafc;
        padding: 0.875rem 1rem;
        text-align: right;
        font-weight: 600;
        color: #4a5568;
        font-size: 0.875rem;
        border-bottom: 1px solid #e2e8f0;
    }

    .data-table td {
        padding: 0.875rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        color: #4a5568;
        font-size: 0.875rem;
    }

    .data-table tr:hover td {
        background: #f7fafc;
    }

    .device-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.375rem;
        padding: 0.25rem 0.625rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .device-badge.desktop { background: #ebf8ff; color: #3182ce; }
    .device-badge.mobile { background: #f0fff4; color: #38a169; }
    .device-badge.tablet { background: #faf5ff; color: #805ad5; }

    .browser-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        background: #edf2f7;
        color: #4a5568;
    }

    .page-url {
        max-width: 250px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        direction: ltr;
        text-align: left;
    }

    .ip-address {
        font-family: monospace;
        font-size: 0.8rem;
        color: #2d3748;
        background: #edf2f7;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .time-ago {
        color: #718096;
        font-size: 0.8rem;
    }

    /* Pagination */
    .pagination-wrapper {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: center;
    }

    /* Mini Charts */
    .mini-chart {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .mini-chart:last-child {
        border-bottom: none;
    }

    .mini-chart-bar {
        flex: 1;
        height: 8px;
        background: #edf2f7;
        border-radius: 4px;
        overflow: hidden;
    }

    .mini-chart-fill {
        height: 100%;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .mini-chart-label {
        font-size: 0.875rem;
        color: #4a5568;
        min-width: 80px;
    }

    .mini-chart-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: #2d3748;
        min-width: 40px;
        text-align: left;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #718096;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 1rem;
        color: #cbd5e0;
    }

    /* Tailwind-Style Pagination */
    .pagination-wrapper nav {
        display: flex;
        justify-content: center;
    }

    .pagination {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.25rem;
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .pagination li a,
    .pagination li span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        height: 40px;
        padding: 0 0.875rem;
        border-radius: 0.5rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #4b5563;
        background: #ffffff;
        border: 1px solid #d1d5db;
        text-decoration: none;
        transition: all 0.15s ease-in-out;
    }

    .pagination li a:hover:not(:disabled) {
        background: #f3f4f6;
        border-color: #9ca3af;
        color: #1f2937;
    }

    .pagination li.active span {
        background: #4f46e5;
        color: #ffffff;
        border-color: #4f46e5;
        font-weight: 600;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
    }

    .pagination li.disabled span {
        color: #9ca3af;
        background: #f9fafb;
        border-color: #e5e7eb;
        cursor: not-allowed;
        opacity: 0.6;
    }

    /* Previous/Next buttons */
    .pagination li:first-child a,
    .pagination li:first-child span,
    .pagination li:last-child a,
    .pagination li:last-child span {
        padding: 0 1rem;
        font-weight: 500;
    }

    /* Focus states */
    .pagination li a:focus {
        outline: 2px solid #4f46e5;
        outline-offset: 2px;
    }

    /* Responsive */
    @media (max-width: 640px) {
        .pagination {
            gap: 0.125rem;
        }

        .pagination li a,
        .pagination li span {
            min-width: 36px;
            height: 36px;
            padding: 0 0.625rem;
            font-size: 0.8125rem;
        }

        .pagination li:first-child a,
        .pagination li:first-child span,
        .pagination li:last-child a,
        .pagination li:last-child span {
            padding: 0 0.75rem;
        }
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-line"></i>
        إحصائيات الزوار
    </h1>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-value">{{ number_format($stats['today']) }}</div>
        <div class="stat-label">زوار اليوم</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">
            <i class="fas fa-user-check"></i>
        </div>
        <div class="stat-value">{{ number_format($stats['unique_today']) }}</div>
        <div class="stat-label">زوار فريدين اليوم</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">
            <i class="fas fa-calendar-week"></i>
        </div>
        <div class="stat-value">{{ number_format($stats['week']) }}</div>
        <div class="stat-label">زوار هذا الأسبوع</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-value">{{ number_format($stats['month']) }}</div>
        <div class="stat-label">زوار هذا الشهر</div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red">
            <i class="fas fa-globe"></i>
        </div>
        <div class="stat-value">{{ number_format($stats['total']) }}</div>
        <div class="stat-label">إجمالي الزوار</div>
    </div>
</div>

<!-- Charts Section -->
<div class="charts-grid">
    <!-- Daily Visits Chart -->
    <div class="chart-card">
        <div class="chart-title">
            <i class="fas fa-chart-area"></i>
            الزيارات اليومية (آخر 30 يوم)
        </div>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <!-- Side Stats -->
    <div>
        <!-- Device Types -->
        <div class="chart-card" style="margin-bottom: 1.5rem;">
            <div class="chart-title">
                <i class="fas fa-mobile-alt"></i>
                حسب نوع الجهاز
            </div>
            @php
                $totalDevices = array_sum($deviceStats);
            @endphp
            @foreach($deviceStats as $device => $count)
                @php
                    $percentage = $totalDevices > 0 ? round(($count / $totalDevices) * 100) : 0;
                    $deviceIcon = match($device) {
                        'mobile' => 'fa-mobile-alt',
                        'tablet' => 'fa-tablet-alt',
                        default => 'fa-desktop'
                    };
                @endphp
                <div class="mini-chart">
                    <i class="fas {{ $deviceIcon }}" style="color: #667eea; width: 20px;"></i>
                    <span class="mini-chart-label">{{ ucfirst($device) }}</span>
                    <div class="mini-chart-bar">
                        <div class="mini-chart-fill" style="width: {{ $percentage }}%"></div>
                    </div>
                    <span class="mini-chart-value">{{ $count }}</span>
                </div>
            @endforeach
        </div>

        <!-- Top Browsers -->
        <div class="chart-card">
            <div class="chart-title">
                <i class="fab fa-chrome"></i>
                المتصفحات الأكثر استخداماً
            </div>
            @php
                $totalBrowsers = array_sum($browserStats);
            @endphp
            @foreach(array_slice($browserStats, 0, 5, true) as $browser => $count)
                @php
                    $percentage = $totalBrowsers > 0 ? round(($count / $totalBrowsers) * 100) : 0;
                    $browserIcon = match(strtolower($browser)) {
                        'chrome' => 'fa-chrome',
                        'firefox' => 'fa-firefox',
                        'safari' => 'fa-safari',
                        'edge' => 'fa-edge',
                        'opera' => 'fa-opera',
                        default => 'fa-globe'
                    };
                @endphp
                <div class="mini-chart">
                    <i class="fab {{ $browserIcon }}" style="color: #667eea; width: 20px;"></i>
                    <span class="mini-chart-label">{{ $browser }}</span>
                    <div class="mini-chart-bar">
                        <div class="mini-chart-fill" style="width: {{ $percentage }}%"></div>
                    </div>
                    <span class="mini-chart-value">{{ $count }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-card">
    <form method="GET" action="{{ route('admin.visitors.index') }}" class="filter-form">
        <div class="form-group">
            <label class="form-label">البحث</label>
            <input type="text" name="search" class="form-input" placeholder="IP، صفحة، متصفح..." value="{{ request('search') }}">
        </div>

        <div class="form-group">
            <label class="form-label">من تاريخ</label>
            <input type="date" name="date_from" class="form-input" value="{{ request('date_from') }}">
        </div>

        <div class="form-group">
            <label class="form-label">إلى تاريخ</label>
            <input type="date" name="date_to" class="form-input" value="{{ request('date_to') }}">
        </div>

        <div class="form-group">
            <label class="form-label">نوع الجهاز</label>
            <select name="device_type" class="form-select">
                <option value="">الكل</option>
                @foreach($deviceTypes as $type)
                    <option value="{{ $type }}" {{ request('device_type') == $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">المتصفح</label>
            <select name="browser" class="form-select">
                <option value="">الكل</option>
                @foreach($browsers as $browser)
                    <option value="{{ $browser }}" {{ request('browser') == $browser ? 'selected' : '' }}>{{ $browser }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group" style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>
                بحث
            </button>
            <a href="{{ route('admin.visitors.index') }}" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                إعادة
            </a>
        </div>
    </form>
</div>

<!-- Visitors Table -->
<div class="table-card">
    <div class="table-header">
        <div class="table-title">
            <i class="fas fa-list"></i>
            سجل الزوار
        </div>
        <div>
            <span style="color: #718096; font-size: 0.875rem;">
                إجمالي: {{ $visitors->total() }} زائر
            </span>
        </div>
    </div>

    @if($visitors->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th>الوقت</th>
                    <th>IP</th>
                    <th>الصفحة</th>
                    <th>الجهاز</th>
                    <th>المتصفح</th>
                    <th>النظام</th>
                    <th>المصدر</th>
                </tr>
            </thead>
            <tbody>
                @foreach($visitors as $visitor)
                    <tr>
                        <td>
                            <div>{{ $visitor->visited_at->format('Y-m-d') }}</div>
                            <div class="time-ago">{{ $visitor->visited_at->format('H:i') }}</div>
                        </td>
                        <td>
                            <span class="ip-address">{{ $visitor->ip_address }}</span>
                        </td>
                        <td>
                            <div class="page-url" title="{{ $visitor->page_url }}">
                                {{ Str::limit($visitor->page_url, 40) }}
                            </div>
                        </td>
                        <td>
                            @if($visitor->device_type)
                                <span class="device-badge {{ $visitor->device_type }}">
                                    <i class="fas fa-{{ $visitor->device_type == 'mobile' ? 'mobile-alt' : ($visitor->device_type == 'tablet' ? 'tablet-alt' : 'desktop') }}"></i>
                                    {{ ucfirst($visitor->device_type) }}
                                </span>
                            @endif
                        </td>
                        <td>
                            @if($visitor->browser)
                                <span class="browser-badge">{{ $visitor->browser }}</span>
                            @endif
                        </td>
                        <td>{{ $visitor->os ?? '-' }}</td>
                        <td>
                            @if($visitor->referrer)
                                <span style="font-size: 0.75rem; color: #718096;" title="{{ $visitor->referrer }}">
                                    {{ Str::limit(parse_url($visitor->referrer, PHP_URL_HOST) ?? $visitor->referrer, 20) }}
                                </span>
                            @else
                                <span style="color: #a0aec0; font-size: 0.75rem;">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($visitors->hasPages())
        <div class="pagination-wrapper">
            <nav aria-label="Pagination" class="pagination">
                {{-- Previous Page Link --}}
                @if($visitors->onFirstPage())
                    <span class="disabled" aria-disabled="true" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </span>
                @else
                    <a href="{{ $visitors->previousPageUrl() }}" rel="prev" aria-label="السابق">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                @endif

                {{-- Pagination Elements --}}
                @foreach($visitors->getUrlRange(1, $visitors->lastPage()) as $page => $url)
                    @if($page == $visitors->currentPage())
                        <span class="active" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if($visitors->hasMorePages())
                    <a href="{{ $visitors->nextPageUrl() }}" rel="next" aria-label="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                @else
                    <span class="disabled" aria-disabled="true" aria-label="التالي">
                        <i class="fas fa-chevron-left"></i>
                    </span>
                @endif
            </nav>
        </div>
        @endif
    @else
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h3>لا توجد زيارات</h3>
            <p>لم يتم تسجيل أي زيارات بعد</p>
        </div>
    @endif
</div>

<!-- Top Pages -->
@if(count($topPages) > 0)
<div class="chart-card" style="margin-top: 1.5rem;">
    <div class="chart-title">
        <i class="fas fa-fire"></i>
        الصفحات الأكثر زيارة
    </div>
    @php
        $maxPageVisits = max($topPages);
    @endphp
    @foreach($topPages as $page => $visits)
        @php
            $percentage = $maxPageVisits > 0 ? round(($visits / $maxPageVisits) * 100) : 0;
        @endphp
        <div class="mini-chart">
            <i class="fas fa-file-alt" style="color: #667eea; width: 20px;"></i>
            <span class="mini-chart-label" style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; direction: ltr; text-align: left;">
                {{ Str::limit($page, 50) }}
            </span>
            <div class="mini-chart-bar" style="width: 200px;">
                <div class="mini-chart-fill" style="width: {{ $percentage }}%"></div>
            </div>
            <span class="mini-chart-value">{{ $visits }}</span>
        </div>
    @endforeach
</div>
@endif
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Daily Visits Chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyData = @json($dailyStats);

    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(d => d.day),
            datasets: [{
                label: 'الزيارات',
                data: dailyData.map(d => d.count),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#667eea',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#2d3748',
                    titleColor: '#fff',
                    bodyColor: '#fff',
                    borderColor: '#667eea',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y + ' زيارة';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#f0f0f0'
                    },
                    ticks: {
                        color: '#718096',
                        font: {
                            size: 11
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color: '#718096',
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
</script>
@endpush
