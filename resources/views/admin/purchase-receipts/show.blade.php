@extends('admin.layout')

@section('title', 'تفاصيل إيصال الاستلام')

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1><i class="fas fa-file-invoice"></i> إيصال استلام #{{ $receipt->receipt_number }}</h1>
        <p>عرض تفاصيل الأصناف والكميات الموردة وتاريخ الاستلام وتحديثات المخازن</p>
    </div>
    <div class="page-actions" style="display: flex; gap: 0.75rem;">
        <a href="{{ route('admin.purchase-receipts.index') }}" class="btn btn-secondary" style="border: 1px solid var(--border-color);">
            <i class="fas fa-arrow-right"></i> عودة
        </a>
        <a href="{{ route('admin.purchase-receipts.edit', $receipt) }}" class="btn btn-primary" style="background-color: var(--warning-dark); border-color: var(--warning-dark);">
            <i class="fas fa-edit"></i> تعديل الإيصال
        </a>
    </div>
</div>

<!-- Status Timeline Tracker -->
<div class="card" style="margin-bottom: 2rem; padding: 1.5rem 1rem;">
    <div class="timeline" style="display: flex; justify-content: space-around; align-items: center; position: relative;">
        <!-- Timeline connector line -->
        <div style="position: absolute; top: 24px; left: 10%; right: 10%; height: 4px; background: var(--border-color); z-index: 1;"></div>
        <div style="position: absolute; top: 24px; left: 10%; right: 50%; height: 4px; background: var(--success); z-index: 2;"></div>
        
        <!-- Step 1: Order -->
        <div style="text-align: center; z-index: 3; position: relative;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: {{ $receipt->purchase_order_id ? 'var(--success)' : 'var(--text-light)' }}; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; font-size: 1.2rem; box-shadow: var(--shadow-sm);">
                <i class="fas fa-file-signature"></i>
            </div>
            <strong style="color: var(--text-dark); font-size: 0.9rem;">أمر الشراء المرتبط</strong>
            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">
                {{ $receipt->purchaseOrder?->order_number ?? 'استلام مباشر' }}
            </p>
        </div>

        <!-- Step 2: Receipt -->
        <div style="text-align: center; z-index: 3; position: relative;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--success); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; font-size: 1.2rem; box-shadow: var(--shadow-sm);">
                <i class="fas fa-truck-loading"></i>
            </div>
            <strong style="color: var(--text-dark); font-size: 0.9rem;">توثيق الاستلام</strong>
            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">تم الاستلام بنجاح</p>
        </div>

        <!-- Step 3: Stock Update -->
        <div style="text-align: center; z-index: 3; position: relative;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--success); color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.5rem; font-size: 1.2rem; box-shadow: var(--shadow-sm);">
                <i class="fas fa-boxes"></i>
            </div>
            <strong style="color: var(--text-dark); font-size: 0.9rem;">تحديث المخزن</strong>
            <p style="margin: 0; font-size: 0.8rem; color: var(--text-muted);">أضيفت للمخازن</p>
        </div>
    </div>
</div>

<div class="row" style="display: flex; flex-wrap: wrap; gap: 1.5rem;">
    <!-- Main items table (Right Column) -->
    <div style="flex: 2; min-width: 600px;">
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-header" style="padding: 1.25rem 1.5rem;">
                <h3><i class="fas fa-list" style="color: var(--accent-blue);"></i> الأصناف والكميات المستلمة</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="margin-bottom: 0; width: 100%;">
                        <thead>
                            <tr>
                                <th style="padding: 1rem 1.5rem;">الصنف والمنتج</th>
                                <th style="padding: 1rem 1.5rem; width: 100px; text-align: center;">الكمية</th>
                                <th style="padding: 1rem 1.5rem; width: 130px;">سعر الوحدة</th>
                                <th style="padding: 1rem 1.5rem; width: 130px;">المجموع الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $calcSubtotal = 0; @endphp
                            @foreach($receipt->items as $item)
                                @php $calcSubtotal += $item->quantity * $item->unit_price; @endphp
                                <tr>
                                    <td style="padding: 1.25rem 1.5rem;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <i class="fas fa-box" style="color: var(--text-light); font-size: 1.1rem;"></i>
                                            <div>
                                                <strong>{{ $item->product?->name_ar ?? '-' }}</strong>
                                                <small style="display: block; color: var(--text-muted);">SKU: {{ $item->product?->sku ?? '-' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 1.25rem 1.5rem; text-align: center; font-weight: 700;">{{ $item->quantity }}</td>
                                    <td style="padding: 1.25rem 1.5rem; color: var(--text-dark);">${{ number_format($item->unit_price, 2) }}</td>
                                    <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text-dark);">${{ number_format($item->total, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Billing Summary Receipt style box -->
                <div style="background-color: var(--bg-light); border-top: 1px solid var(--border-color); padding: 1.5rem 2rem; display: flex; justify-content: flex-end;">
                    <div style="width: 320px;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem; color: var(--text-medium);">
                            <span>المجموع الفرعي:</span>
                            <span>${{ number_format($calcSubtotal, 2) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 2px solid var(--border-color); padding-top: 0.75rem; font-weight: 700; color: var(--text-dark); font-size: 1.1rem;">
                            <span>الإجمالي الكلي:</span>
                            <span style="color: var(--accent-blue);">${{ number_format($calcSubtotal, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Log Notification Alert -->
        <div class="alert alert-success" style="border-radius: var(--radius-md); padding: 1.25rem 1.5rem; display: flex; gap: 1rem; align-items: flex-start; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46;">
            <i class="fas fa-check-circle" style="font-size: 1.5rem; margin-top: 0.15rem; color: #10b981;"></i>
            <div>
                <h4 style="margin: 0 0 0.5rem 0; font-weight: 700;">تم إدخال الكميات للمخزن بنجاح</h4>
                <p style="margin: 0; font-size: 0.9rem; line-height: 1.6;">تم إضافة السلع المستلمة إلى المخزن الفعلي وتحديث مستويات المخزون تلقائياً بالقيم التالية:</p>
                <ul style="margin: 0.5rem 0 0 0; padding-right: 1.25rem; font-size: 0.9rem; line-height: 1.6;">
                    @foreach($receipt->items as $item)
                        <li>{{ $item->product?->name_ar ?? '-' }}: <strong>+{{ $item->quantity }} وحدة</strong></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <!-- Sidebar cards (Left Column) -->
    <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Supplier details -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="padding: 1.25rem 1.5rem;">
                <h3><i class="fas fa-user-tie" style="color: var(--accent-blue);"></i> بيانات المورد</h3>
            </div>
            <div class="card-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">اسم المورد:</span>
                    <strong>{{ $receipt->supplier?->name ?? '-' }}</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">الشركة:</span>
                    <strong>{{ $receipt->supplier?->company ?? '-' }}</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">البريد الإلكتروني:</span>
                    <strong>{{ $receipt->supplier?->email ?? '-' }}</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">الهاتف:</span>
                    <strong>{{ $receipt->supplier?->phone ?? '-' }}</strong>
                </div>
            </div>
        </div>

        <!-- Receipt Shipment info -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="padding: 1.25rem 1.5rem;">
                <h3><i class="fas fa-info-circle" style="color: var(--warning-dark);"></i> تفاصيل الشحنة والاستلام</h3>
            </div>
            <div class="card-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9rem;">
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">تاريخ الاستلام الفعلي:</span>
                    <strong>{{ $receipt->receipt_date?->format('Y-m-d') ?? '-' }}</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">سجل الاستلام بواسطة:</span>
                    <strong>{{ $receipt->creator?->name ?? 'غير محدد' }}</strong>
                </div>
                <div>
                    <span style="color: var(--text-muted); display: block; margin-bottom: 0.15rem;">مرتبط بأمر شراء:</span>
                    @if($receipt->purchaseOrder)
                        <strong style="color: var(--accent-blue);">#{{ $receipt->purchaseOrder->order_number }}</strong>
                    @else
                        <strong>شراء مباشر</strong>
                    @endif
                </div>
            </div>
        </div>

        <!-- Receipt notes -->
        @if($receipt->notes)
            <div class="card" style="margin-bottom: 0;">
                <div class="card-header" style="padding: 1.25rem 1.5rem;">
                    <h3><i class="fas fa-sticky-note" style="color: var(--text-medium);"></i> ملاحظات الاستلام</h3>
                </div>
                <div class="card-body" style="padding: 1.25rem 1.5rem; line-height: 1.6; font-size: 0.9rem; color: var(--text-medium);">
                    {{ $receipt->notes }}
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
