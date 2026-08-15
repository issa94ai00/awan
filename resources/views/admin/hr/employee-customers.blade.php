@extends('admin.layout')

@section('title', 'إدارة علاقة الموظف بالعملاء')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-user-tie"></i> إدارة علاقة الموظف بالعملاء</h1>
    <a href="{{ route('admin.hr.index') }}" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> عودة
    </a>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> فلترة حسب الموظف</h3>
    </div>
    <div class="card-body">
        <div class="form-group">
            <label>اختر الموظف:</label>
            <select id="employeeSelect" class="form-control">
                <option value="">-- اختر موظف --</option>
            </select>
        </div>
    </div>
</div>

<div class="card" id="employeeCustomersCard" style="display: none;">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> العملاء المرتبطين</h3>
        <button class="btn btn-primary" id="attachCustomersBtn">
            <i class="fas fa-plus"></i> إضافة عملاء
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table" id="customersTable">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الهاتف</th>
                        <th>الشركة</th>
                        <th>إجمالي المشتريات</th>
                        <th>آخر شراء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody id="customersTableBody">
                    <tr>
                        <td colspan="7" class="text-center">جاري التحميل...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal for attaching customers -->
<div class="modal" id="attachCustomersModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> إضافة عملاء للموظف</h3>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>البحث عن العملاء:</label>
                <input type="text" id="customerSearch" class="form-control" placeholder="ابحث باسم العميل أو البريد الإلكتروني...">
            </div>
            <div class="form-group">
                <label>العملاء المتاحين:</label>
                <div id="availableCustomers" class="customers-list">
                    <p class="text-center">جاري البحث...</p>
                </div>
            </div>
            <div class="form-group">
                <label>العملاء المحددين:</label>
                <div id="selectedCustomers" class="selected-customers">
                    <p class="text-center">لم يتم تحديد أي عملاء</p>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="cancelAttach">إلغاء</button>
            <button class="btn btn-primary" id="confirmAttach">إضافة العملاء</button>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
.customers-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
}

.customer-item {
    display: flex;
    align-items: center;
    padding: 8px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}

.customer-item:hover {
    background-color: #f5f5f5;
}

.customer-item input[type="checkbox"] {
    margin-left: 10px;
}

.customer-info {
    flex: 1;
}

.customer-name {
    font-weight: 600;
    margin-bottom: 2px;
}

.customer-email {
    font-size: 12px;
    color: #666;
}

.selected-customers {
    min-height: 50px;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background-color: #f9f9f9;
}

.selected-customer-tag {
    display: inline-flex;
    align-items: center;
    background-color: #667eea;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    margin: 2px;
    font-size: 12px;
}

.selected-customer-tag .remove-btn {
    margin-right: 5px;
    cursor: pointer;
    font-weight: bold;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    border: none;
    background: none;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.btn-primary {
    background-color: #667eea;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-danger {
    background-color: #dc3545;
    color: white;
}

.btn-sm {
    padding: 4px 8px;
    font-size: 12px;
}
</style>
@endpush

@push('scripts')
<script>
let selectedEmployeeId = null;
let selectedCustomerIds = new Set();
let allAvailableCustomers = [];

// Load employees on page load
document.addEventListener('DOMContentLoaded', function() {
    loadEmployees();
    
    // Event listeners
    document.getElementById('employeeSelect').addEventListener('change', function() {
        selectedEmployeeId = this.value;
        if (selectedEmployeeId) {
            loadEmployeeCustomers(selectedEmployeeId);
            document.getElementById('employeeCustomersCard').style.display = 'block';
        } else {
            document.getElementById('employeeCustomersCard').style.display = 'none';
        }
    });
    
    document.getElementById('attachCustomersBtn').addEventListener('click', openAttachModal);
    document.getElementById('closeModal').addEventListener('click', closeAttachModal);
    document.getElementById('cancelAttach').addEventListener('click', closeAttachModal);
    document.getElementById('confirmAttach').addEventListener('click', attachCustomers);
    
    document.getElementById('customerSearch').addEventListener('input', debounce(function() {
        searchCustomers(this.value);
    }, 300));
});

function loadEmployees() {
    fetch('/api/v1/admin/employees')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('employeeSelect');
                select.innerHTML = '<option value="">-- اختر موظف --</option>';
                data.data.employees.forEach(employee => {
                    select.innerHTML += `<option value="${employee.id}">${employee.name}</option>`;
                });
            }
        })
        .catch(error => console.error('Error loading employees:', error));
}

function loadEmployeeCustomers(employeeId) {
    fetch(`/api/v1/admin/employees/${employeeId}/customers`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCustomers(data.data);
            }
        })
        .catch(error => {
            console.error('Error loading customers:', error);
            document.getElementById('customersTableBody').innerHTML = '<tr><td colspan="7" class="text-center">حدث خطأ في التحميل</td></tr>';
        });
}

function displayCustomers(customers) {
    const tbody = document.getElementById('customersTableBody');
    
    if (customers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">لا يوجد عملاء مرتبطين</td></tr>';
        return;
    }
    
    tbody.innerHTML = customers.map(customer => `
        <tr>
            <td>${customer.name}</td>
            <td>${customer.email || '-'}</td>
            <td>${customer.phone || '-'}</td>
            <td>${customer.company || '-'}</td>
            <td>${customer.total_purchases ? numberFormat(customer.total_purchases) : '0'}</td>
            <td>${customer.last_purchase_at ? formatDate(customer.last_purchase_at) : '-'}</td>
            <td>
                <button class="btn btn-danger btn-sm" onclick="detachCustomer(${customer.id})">
                    <i class="fas fa-unlink"></i> فك الارتباط
                </button>
            </td>
        </tr>
    `).join('');
}

function openAttachModal() {
    selectedCustomerIds.clear();
    updateSelectedCustomersDisplay();
    document.getElementById('customerSearch').value = '';
    document.getElementById('availableCustomers').innerHTML = '<p class="text-center">جاري البحث...</p>';
    document.getElementById('attachCustomersModal').style.display = 'block';
    searchCustomers('');
}

function closeAttachModal() {
    document.getElementById('attachCustomersModal').style.display = 'none';
}

function searchCustomers(query) {
    fetch(`/api/v1/admin/sales/customers?search=${query}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                allAvailableCustomers = data.data.customers || [];
                displayAvailableCustomers(allAvailableCustomers);
            }
        })
        .catch(error => {
            console.error('Error searching customers:', error);
            document.getElementById('availableCustomers').innerHTML = '<p class="text-center">حدث خطأ في البحث</p>';
        });
}

function displayAvailableCustomers(customers) {
    const container = document.getElementById('availableCustomers');
    
    if (customers.length === 0) {
        container.innerHTML = '<p class="text-center">لا يوجد عملاء</p>';
        return;
    }
    
    container.innerHTML = customers.map(customer => `
        <div class="customer-item" onclick="toggleCustomerSelection(${customer.id})">
            <input type="checkbox" id="customer_${customer.id}" ${selectedCustomerIds.has(customer.id) ? 'checked' : ''}>
            <div class="customer-info">
                <div class="customer-name">${customer.name}</div>
                <div class="customer-email">${customer.email || 'لا يوجد بريد'}</div>
            </div>
        </div>
    `).join('');
}

function toggleCustomerSelection(customerId) {
    const checkbox = document.getElementById(`customer_${customerId}`);
    
    if (selectedCustomerIds.has(customerId)) {
        selectedCustomerIds.delete(customerId);
        checkbox.checked = false;
    } else {
        selectedCustomerIds.add(customerId);
        checkbox.checked = true;
    }
    
    updateSelectedCustomersDisplay();
}

function updateSelectedCustomersDisplay() {
    const container = document.getElementById('selectedCustomers');
    
    if (selectedCustomerIds.size === 0) {
        container.innerHTML = '<p class="text-center">لم يتم تحديد أي عملاء</p>';
        return;
    }
    
    const selectedCustomers = allAvailableCustomers.filter(c => selectedCustomerIds.has(c.id));
    container.innerHTML = selectedCustomers.map(customer => `
        <span class="selected-customer-tag">
            <span class="remove-btn" onclick="removeCustomerSelection(${customer.id})">&times;</span>
            ${customer.name}
        </span>
    `).join('');
}

function removeCustomerSelection(customerId) {
    selectedCustomerIds.delete(customerId);
    const checkbox = document.getElementById(`customer_${customerId}`);
    if (checkbox) checkbox.checked = false;
    updateSelectedCustomersDisplay();
}

function attachCustomers() {
    if (selectedCustomerIds.size === 0) {
        alert('الرجاء تحديد عملاء على الأقل');
        return;
    }
    
    const customerIds = Array.from(selectedCustomerIds);
    
    fetch(`/api/v1/admin/employees/${selectedEmployeeId}/customers/attach`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ customer_ids: customerIds })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم إضافة العملاء بنجاح');
            closeAttachModal();
            loadEmployeeCustomers(selectedEmployeeId);
        } else {
            alert('حدث خطأ: ' + (data.message || 'غير معروف'));
        }
    })
    .catch(error => {
        console.error('Error attaching customers:', error);
        alert('حدث خطأ في الاتصال');
    });
}

function detachCustomer(customerId) {
    if (!confirm('هل أنت متأكد من فك الارتباط؟')) {
        return;
    }
    
    fetch(`/api/v1/admin/employees/${selectedEmployeeId}/customers/detach`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ customer_ids: [customerId] })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('تم فك الارتباط بنجاح');
            loadEmployeeCustomers(selectedEmployeeId);
        } else {
            alert('حدث خطأ: ' + (data.message || 'غير معروف'));
        }
    })
    .catch(error => {
        console.error('Error detaching customer:', error);
        alert('حدث خطأ في الاتصال');
    });
}

function numberFormat(num) {
    return new Intl.NumberFormat('ar-SA').format(num);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-SA');
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>
@endpush
