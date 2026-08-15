@extends('admin.layout')

@section('title', 'تقارير المبيعات الاحترافية')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-chart-line"></i> تقارير المبيعات الاحترافية</h1>
    <a href="{{ route('admin.reports.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<!-- Filters Section -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> خيارات التصفية</h3>
        <button class="btn btn-primary btn-sm" id="applyFilters">
            <i class="fas fa-search"></i> تطبيق الفلاتر
        </button>
        <button class="btn btn-secondary btn-sm" id="resetFilters">
            <i class="fas fa-undo"></i> إعادة تعيين
        </button>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group">
                    <label>الموظف:</label>
                    <select id="employeeFilter" class="form-control">
                        <option value="">-- جميع الموظفين --</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>حالة الطلب:</label>
                    <select id="statusFilter" class="form-control">
                        <option value="">-- جميع الحالات --</option>
                        <option value="pending">معلق</option>
                        <option value="confirmed">مؤكد</option>
                        <option value="processing">قيد المعالجة</option>
                        <option value="shipped">تم الشحن</option>
                        <option value="delivered">تم التسليم</option>
                        <option value="cancelled">ملغي</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>نوع التصفية الزمنية:</label>
                    <select id="dateFilterType" class="form-control">
                        <option value="all">الكل</option>
                        <option value="today">اليوم</option>
                        <option value="yesterday">أمس</option>
                        <option value="this_week">هذا الأسبوع</option>
                        <option value="this_month">هذا الشهر</option>
                        <option value="last_month">الشهر الماضي</option>
                        <option value="custom">مجال مخصص</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <label>طريقة العرض:</label>
                    <select id="groupBy" class="form-control">
                        <option value="day">يومي</option>
                        <option value="week">أسبوعي</option>
                        <option value="month">شهري</option>
                        <option value="employee">حسب الموظف</option>
                        <option value="status">حسب الحالة</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="row" id="customDateRange" style="display: none;">
            <div class="col-md-6">
                <div class="form-group">
                    <label>من تاريخ:</label>
                    <input type="date" id="startDate" class="form-control">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>إلى تاريخ:</label>
                    <input type="date" id="endDate" class="form-control">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-top: 20px;">
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="totalOrders">0</h3>
            <p>إجمالي الطلبات</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-shopping-cart"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="totalSales">0</h3>
            <p>إجمالي المبيعات</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="totalSubtotal">0</h3>
            <p>إجمالي المجموع</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-coins"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="totalDiscount">0</h3>
            <p>إجمالي الخصومات</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-tags"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="totalTax">0</h3>
            <p>إجمالي الضرائب</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-percentage"></i>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-info">
            <h3 id="avgOrderValue">0</h3>
            <p>متوسط قيمة الطلب</p>
        </div>
        <div class="stat-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div class="dashboard-grid" style="margin-top: 20px;">
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> المبيعات حسب الفترة</h3>
        </div>
        <div class="card-body">
            <canvas id="salesChart" height="300"></canvas>
        </div>
    </div>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> التوزيع حسب المعيار</h3>
        </div>
        <div class="card-body">
            <canvas id="distributionChart" height="300"></canvas>
        </div>
    </div>
</div>

<!-- Detailed Report Table -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-table"></i> تقرير مفصل</h3>
        <button class="btn btn-success btn-sm" id="exportReport">
            <i class="fas fa-download"></i> تصدير التقرير
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="reportTable">
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>التاريخ</th>
                        <th>العميل</th>
                        <th>الموظف</th>
                        <th>الحالة</th>
                        <th>المجموع</th>
                        <th>الخصم</th>
                        <th>الضريبة</th>
                        <th>الإجمالي</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr>
                        <td colspan="9" class="text-center">جاري التحميل...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination-container" id="paginationContainer">
        </div>
    </div>
</div>

<!-- Top Performers Section -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-trophy"></i> أفضل الموظفين أداءً</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="topPerformersTable">
                <thead>
                    <tr>
                        <th>الترتيب</th>
                        <th>الموظف</th>
                        <th>عدد الطلبات</th>
                        <th>إجمالي المبيعات</th>
                        <th>متوسط قيمة الطلب</th>
                    </tr>
                </thead>
                <tbody id="topPerformersBody">
                    <tr>
                        <td colspan="5" class="text-center">جاري التحميل...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.row {
    display: flex;
    flex-wrap: wrap;
    margin: -10px;
}

.col-md-3, .col-md-6 {
    flex: 0 0 calc(25% - 20px);
    padding: 10px;
    max-width: calc(25% - 20px);
}

.col-md-6 {
    flex: 0 0 calc(50% - 20px);
    max-width: calc(50% - 20px);
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
}

.card-header h3 {
    margin: 0;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-primary {
    background-color: #667eea;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-info h3 {
    margin: 0;
    font-size: 28px;
    color: #667eea;
}

.stat-info p {
    margin: 5px 0 0;
    color: #666;
    font-size: 14px;
}

.stat-icon {
    font-size: 40px;
    color: #667eea;
    opacity: 0.3;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.dashboard-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.card-body {
    padding: 20px;
}

.table-responsive {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th, .table td {
    padding: 12px;
    text-align: right;
    border-bottom: 1px solid #eee;
}

.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.text-center {
    text-align: center;
}

.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    padding: 20px 0;
}

.pagination-btn {
    padding: 8px 12px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    border-radius: 4px;
}

.pagination-btn:hover {
    background-color: #f5f5f5;
}

.pagination-btn.active {
    background-color: #667eea;
    color: white;
    border-color: #667eea;
}

.pagination-info {
    color: #666;
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let salesChart = null;
let distributionChart = null;
let currentPage = 1;
let totalPages = 1;

document.addEventListener('DOMContentLoaded', function() {
    loadEmployees();
    loadReport();
    loadTopPerformers();
    
    // Event listeners
    document.getElementById('applyFilters').addEventListener('click', applyFilters);
    document.getElementById('resetFilters').addEventListener('click', resetFilters);
    document.getElementById('dateFilterType').addEventListener('change', handleDateFilterChange);
    document.getElementById('exportReport').addEventListener('click', exportReport);
});

function loadEmployees() {
    fetch('/api/v1/admin/employees')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('employeeFilter');
                select.innerHTML = '<option value="">-- جميع الموظفين --</option>';
                data.data.employees.forEach(employee => {
                    select.innerHTML += `<option value="${employee.id}">${employee.name}</option>`;
                });
            }
        })
        .catch(error => console.error('Error loading employees:', error));
}

function handleDateFilterChange() {
    const filterType = document.getElementById('dateFilterType').value;
    const customRange = document.getElementById('customDateRange');
    
    if (filterType === 'custom') {
        customRange.style.display = 'block';
    } else {
        customRange.style.display = 'none';
    }
}

function getFilterParams() {
    const employeeId = document.getElementById('employeeFilter').value;
    const status = document.getElementById('statusFilter').value;
    const dateFilterType = document.getElementById('dateFilterType').value;
    const groupBy = document.getElementById('groupBy').value;
    
    let params = {};
    
    if (employeeId) params.employee_id = employeeId;
    if (status) params.status = status;
    if (groupBy) params.group_by = groupBy;
    
    // Date filters
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    const startOfWeek = new Date(today);
    startOfWeek.setDate(today.getDate() - today.getDay());
    
    const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
    
    const lastMonthStart = new Date(today.getFullYear(), today.getMonth() - 1, 1);
    const lastMonthEnd = new Date(today.getFullYear(), today.getMonth(), 0);
    
    switch(dateFilterType) {
        case 'today':
            params.date = formatDateForAPI(today);
            break;
        case 'yesterday':
            params.date = formatDateForAPI(yesterday);
            break;
        case 'this_week':
            params.start_date = formatDateForAPI(startOfWeek);
            params.end_date = formatDateForAPI(today);
            break;
        case 'this_month':
            params.start_date = formatDateForAPI(startOfMonth);
            params.end_date = formatDateForAPI(endOfMonth);
            break;
        case 'last_month':
            params.start_date = formatDateForAPI(lastMonthStart);
            params.end_date = formatDateForAPI(lastMonthEnd);
            break;
        case 'custom':
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (startDate) params.start_date = startDate;
            if (endDate) params.end_date = endDate;
            break;
    }
    
    return params;
}

function formatDateForAPI(date) {
    return date.toISOString().split('T')[0];
}

function applyFilters() {
    currentPage = 1;
    loadReport();
    loadTopPerformers();
}

function resetFilters() {
    document.getElementById('employeeFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('dateFilterType').value = 'all';
    document.getElementById('groupBy').value = 'day';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('customDateRange').style.display = 'none';
    
    currentPage = 1;
    loadReport();
    loadTopPerformers();
}

function loadReport() {
    const params = getFilterParams();
    params.page = currentPage;
    params.per_page = 20;
    
    const queryString = new URLSearchParams(params).toString();
    
    fetch(`/api/v1/admin/reports/sales?${queryString}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSummaryCards(data.data.summary);
                updateReportTable(data.data.sales_orders);
                updatePagination(data.data.pagination);
                loadSummaryChart(params);
            }
        })
        .catch(error => {
            console.error('Error loading report:', error);
            document.getElementById('reportTableBody').innerHTML = '<tr><td colspan="9" class="text-center">حدث خطأ في التحميل</td></tr>';
        });
}

function loadSummaryChart(params) {
    const chartParams = {...params};
    delete chartParams.page;
    delete chartParams.per_page;
    
    const queryString = new URLSearchParams(chartParams).toString();
    
    fetch(`/api/v1/admin/reports/sales/summary?${queryString}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCharts(data.data.summary, data.data.group_by);
            }
        })
        .catch(error => console.error('Error loading summary:', error));
}

function updateSummaryCards(summary) {
    document.getElementById('totalOrders').textContent = summary.total_orders || 0;
    document.getElementById('totalSales').textContent = numberFormat(summary.total_sales || 0);
    document.getElementById('totalSubtotal').textContent = numberFormat(summary.total_subtotal || 0);
    document.getElementById('totalDiscount').textContent = numberFormat(summary.total_discount || 0);
    document.getElementById('totalTax').textContent = numberFormat(summary.total_tax || 0);
    document.getElementById('avgOrderValue').textContent = numberFormat(summary.average_order_value || 0);
}

function updateReportTable(orders) {
    const tbody = document.getElementById('reportTableBody');
    
    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">لا توجد بيانات</td></tr>';
        return;
    }
    
    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>${order.order_number || '-'}</td>
            <td>${order.order_date ? formatDate(order.order_date) : '-'}</td>
            <td>${order.customer ? order.customer.name : '-'}</td>
            <td>${order.assigned_employee ? order.assigned_employee.name : '-'}</td>
            <td><span class="status-badge status-${order.status}">${getStatusText(order.status)}</span></td>
            <td>${numberFormat(order.subtotal || 0)}</td>
            <td>${numberFormat(order.discount || 0)}</td>
            <td>${numberFormat(order.tax || 0)}</td>
            <td><strong>${numberFormat(order.total || 0)}</strong></td>
        </tr>
    `).join('');
}

function updatePagination(pagination) {
    if (!pagination) return;
    
    currentPage = pagination.current_page;
    totalPages = pagination.last_page;
    
    const container = document.getElementById('paginationContainer');
    
    let html = `
        <button class="pagination-btn" ${currentPage === 1 ? 'disabled' : ''} onclick="changePage(${currentPage - 1})">
            <i class="fas fa-chevron-right"></i>
        </button>
    `;
    
    for (let i = 1; i <= totalPages; i++) {
        html += `<button class="pagination-btn ${i === currentPage ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
    }
    
    html += `
        <button class="pagination-btn" ${currentPage === totalPages ? 'disabled' : ''} onclick="changePage(${currentPage + 1})">
            <i class="fas fa-chevron-left"></i>
        </button>
        <span class="pagination-info">صفحة ${currentPage} من ${totalPages} - إجمالي ${pagination.total} سجل</span>
    `;
    
    container.innerHTML = html;
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadReport();
}

function updateCharts(summary, groupBy) {
    if (!summary || !Array.isArray(summary)) return;
    
    const labels = summary.map(item => {
        switch(groupBy) {
            case 'employee': return item.employee_name;
            case 'status': return item.status_text;
            default: return item.date || item.period;
        }
    });
    
    const salesData = summary.map(item => item.total_sales || 0);
    const ordersData = summary.map(item => item.total_orders || 0);
    
    // Sales Chart
    const salesCtx = document.getElementById('salesChart').getContext('2d');
    
    if (salesChart) salesChart.destroy();
    
    salesChart = new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'المبيعات',
                data: salesData,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'عدد الطلبات',
                data: ordersData,
                borderColor: '#f093fb',
                backgroundColor: 'rgba(240, 147, 251, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    
    // Distribution Chart
    const distCtx = document.getElementById('distributionChart').getContext('2d');
    
    if (distributionChart) distributionChart.destroy();
    
    distributionChart = new Chart(distCtx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: salesData,
                backgroundColor: [
                    '#667eea', '#f093fb', '#4facfe', '#43e97b', 
                    '#fa709a', '#fee140', '#30cfd0', '#a8edea'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                }
            }
        }
    });
}

function loadTopPerformers() {
    const params = getFilterParams();
    delete params.group_by;
    
    const queryString = new URLSearchParams(params).toString();
    
    fetch(`/api/v1/admin/reports/sales/top-performers?${queryString}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateTopPerformersTable(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading top performers:', error);
            document.getElementById('topPerformersBody').innerHTML = '<tr><td colspan="5" class="text-center">حدث خطأ في التحميل</td></tr>';
        });
}

function updateTopPerformersTable(performers) {
    const tbody = document.getElementById('topPerformersBody');
    
    if (!performers || performers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">لا توجد بيانات</td></tr>';
        return;
    }
    
    tbody.innerHTML = performers.map((performer, index) => `
        <tr>
            <td>
                <span class="rank-badge rank-${index + 1}">
                    ${index + 1}
                </span>
            </td>
            <td>${performer.employee_name}</td>
            <td>${performer.total_orders}</td>
            <td><strong>${numberFormat(performer.total_sales)}</strong></td>
            <td>${numberFormat(performer.average_order_value)}</td>
        </tr>
    `).join('');
}

function exportReport() {
    const params = getFilterParams();
    const queryString = new URLSearchParams(params).toString();
    
    window.open(`/api/v1/admin/reports/sales/export?${queryString}`, '_blank');
}

function numberFormat(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA');
}

function getStatusText(status) {
    const statusMap = {
        'pending': 'معلق',
        'confirmed': 'مؤكد',
        'processing': 'قيد المعالجة',
        'shipped': 'تم الشحن',
        'delivered': 'تم التسليم',
        'cancelled': 'ملغي'
    };
    return statusMap[status] || status;
}
</script>
@endpush
