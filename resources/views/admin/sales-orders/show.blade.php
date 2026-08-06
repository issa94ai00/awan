@extends('admin.layout')

@section('title', 'تفاصيل طلب البيع')

@push('styles')
<style>
    .show-grid {
        display: grid;
        grid-template-columns: 2.2fr 1fr;
        gap: 1.5rem;
    }
    @media (max-width: 992px) {
        .show-grid {
            grid-template-columns: 1fr;
        }
    }
    .info-list p {
        margin-bottom: 0.875rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px dashed var(--border-color);
        padding-bottom: 0.625rem;
        font-size: 0.9rem;
    }
    .info-list p:last-child {
        border-bottom: none;
        padding-bottom: 0;
        margin-bottom: 0;
    }
    .info-list p strong {
        color: var(--text-medium);
        font-weight: 500;
    }
    .info-list p span {
        color: var(--text-dark);
        font-weight: 600;
    }
</style>
@endpush

@section('content')
<div class="page-header">
    <div class="page-title">
        <h1>
            <i class="fas fa-shopping-cart"></i> 
            طلب بيع #{{ $salesOrder->order_number }}
        </h1>
        <p>تاريخ الطلب: {{ $salesOrder->created_at->format('Y-m-d') }} | بواسطة: {{ $salesOrder->creator?->name ?? 'غير معروف' }}</p>
    </div>
    <div class="page-actions">
        <a href="{{ route('admin.sales-orders.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-right"></i> عودة
        </a>
        <a href="{{ route('admin.sales-orders.edit', $salesOrder) }}" class="btn btn-warning" style="color: black;">
            <i class="fas fa-edit"></i> تعديل الطلب
        </a>
    </div>
</div>

<!-- Order Timeline Status Bar -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.5rem 2rem;">
        <h4 style="margin-bottom: 1rem; color: var(--text-medium);"><i class="fas fa-tasks"></i> مسار حالة الطلب</h4>
        <div class="order-timeline" style="display: flex; justify-content: space-between; position: relative; margin: 1.5rem 0;">
            <!-- progress line -->
            <div style="position: absolute; top: 15px; left: 8%; right: 8%; height: 4px; background: var(--border-color); z-index: 1;"></div>
            @php
                $statuses = [
                    'pending' => ['label' => 'معلق', 'icon' => 'fa-clock', 'step' => 1],
                    'confirmed' => ['label' => 'مؤكد', 'icon' => 'fa-check-circle', 'step' => 2],
                    'processing' => ['label' => 'قيد المعالجة', 'icon' => 'fa-sync-alt', 'step' => 3],
                    'shipped' => ['label' => 'تم الشحن', 'icon' => 'fa-shipping-fast', 'step' => 4],
                    'delivered' => ['label' => 'تم التسليم', 'icon' => 'fa-check-double', 'step' => 5],
                ];
                
                $currentStep = match($salesOrder->status) {
                    'pending' => 1,
                    'confirmed' => 2,
                    'processing' => 3,
                    'shipped' => 4,
                    'delivered' => 5,
                    'cancelled' => 0,
                    default => 1
                };
            @endphp
            
            @if($salesOrder->status === 'cancelled')
                <div style="width: 100%; text-align: center; color: var(--danger); font-weight: 700; z-index: 2;">
                    <i class="fas fa-times-circle" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                    <div>تم إلغاء هذا الطلب كلياً</div>
                </div>
            @else
                @foreach($statuses as $key => $status)
                    @php
                        $isActive = $currentStep >= $status['step'];
                        $isCurrent = $currentStep === $status['step'];
                        $color = $isActive ? 'var(--accent-blue)' : 'var(--text-light)';
                        $bg = $isActive ? 'var(--accent-blue)' : 'var(--bg-white)';
                        $textColor = $isActive ? 'var(--text-dark)' : 'var(--text-muted)';
                        $fontWeight = $isActive ? '700' : '500';
                    @endphp
                    <div class="timeline-step" style="display: flex; flex-direction: column; align-items: center; width: 18%; text-align: center; z-index: 2; position: relative;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: {{ $bg }}; border: 2px solid {{ $color }}; display: flex; align-items: center; justify-content: center; color: {{ $isActive ? '#fff' : 'var(--text-light)' }}; font-size: 0.9rem; transition: all 0.3s ease; box-shadow: {{ $isCurrent ? '0 0 0 4px rgba(0, 102, 204, 0.2)' : 'none' }}">
                            <i class="fas {{ $status['icon'] }}"></i>
                        </div>
                        <div style="margin-top: 0.5rem; font-size: 0.85rem; font-weight: {{ $fontWeight }}; color: {{ $textColor }};">{{ $status['label'] }}</div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>

<div class="show-grid">
    <!-- Main Content: Items Table -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3><i class="fas fa-boxes"></i> الأصناف المطلوبة</h3>
                <span class="badge badge-info">{{ $salesOrder->items->count() }} أصناف</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="table-responsive">
                    <table class="table" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th style="padding: 1.25rem 1.5rem; font-weight: 700;">اسم المنتج</th>
                                <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 100px; text-align: center;">الكمية</th>
                                <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 130px;">سعر الوحدة</th>
                                <th style="padding: 1.25rem 1.5rem; font-weight: 700; width: 130px;">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($salesOrder->items as $item)
                            <tr>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <strong style="color: var(--text-dark);">{{ $item->product?->name_ar ?? '-' }}</strong>
                                    @if($item->product?->sku)
                                    <br><small class="text-muted">رمز: {{ $item->product->sku }}</small>
                                    @endif
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center; font-weight: 600;">
                                    {{ $item->quantity }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem; color: var(--text-medium);">
                                    ${{ number_format($item->unit_price, 2) }}
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text-dark);">
                                    ${{ number_format($item->total, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Financial Summary Receipt Box -->
                <div style="display: flex; justify-content: flex-end; padding: 1.5rem; border-top: 1px solid var(--border-color);">
                    <div style="width: 320px; background: var(--bg-light); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                            <span>المجموع الفرعي:</span>
                            <strong>${{ number_format($salesOrder->subtotal, 2) }}</strong>
                        </div>
                        @if($salesOrder->discount > 0)
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem; color: var(--danger-dark);">
                            <span>الخصم:</span>
                            <strong>-${{ number_format($salesOrder->discount, 2) }}</strong>
                        </div>
                        @endif
                        @if($salesOrder->tax > 0)
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.9rem;">
                            <span>الضريبة:</span>
                            <strong>+${{ number_format($salesOrder->tax, 2) }}</strong>
                        </div>
                        @endif
                        <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 0.75rem 0;">
                        <div style="display: flex; justify-content: space-between; font-size: 1.1rem; font-weight: 700; color: var(--accent-blue);">
                            <span>الإجمالي الكلي:</span>
                            <span>${{ number_format($salesOrder->total, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($salesOrder->notes)
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sticky-note"></i> ملاحظات إضافية</h3>
            </div>
            <div class="card-body">
                <p style="margin: 0; color: var(--text-medium); line-height: 1.7;">{{ $salesOrder->notes }}</p>
            </div>
        </div>
        @endif
    </div>

    <!-- Sidebar: Customer & Shipping Details -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Customer Info Card -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3><i class="fas fa-user-tie"></i> بيانات العميل</h3>
            </div>
            <div class="card-body">
                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.25rem;">
                    <i class="fas fa-user-circle" style="font-size: 3rem; color: var(--accent-blue);"></i>
                    <div>
                        <h4 style="margin: 0; color: var(--text-dark);">{{ $salesOrder->customer?->name ?? '-' }}</h4>
                        <span class="text-muted" style="font-size: 0.8rem;">عميل مسجل</span>
                    </div>
                </div>
                <div class="info-list">
                    @if($salesOrder->customer?->phone)
                    <p>
                        <strong>رقم الهاتف:</strong>
                        <span>{{ $salesOrder->customer->phone }}</span>
                    </p>
                    @endif
                    @if($salesOrder->customer?->email)
                    <p>
                        <strong>البريد الإلكتروني:</strong>
                        <span style="font-size: 0.8rem;">{{ $salesOrder->customer->email }}</span>
                    </p>
                    @endif
                    <p>
                        <strong>الرصيد المالي:</strong>
                        <span style="color: {{ ($salesOrder->customer?->balance ?? 0) < 0 ? 'var(--danger-dark)' : 'var(--success-dark)' }};">
                            ${{ number_format($salesOrder->customer?->balance ?? 0, 2) }}
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Order details -->
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> معلومات التوصيل</h3>
            </div>
            <div class="card-body">
                <div class="info-list">
                    <p>
                        <strong>تاريخ الطلب:</strong>
                        <span>{{ $salesOrder->order_date?->format('Y-m-d') }}</span>
                    </p>
                    <p>
                        <strong>التسليم المتوقع:</strong>
                        <span>{{ $salesOrder->expected_delivery?->format('Y-m-d') ?? 'غير محدد' }}</span>
                    </p>
                    @if($salesOrder->quote)
                    <p>
                        <strong>عرض السعر المرجعي:</strong>
                        <span>
                            <a href="/admin/quotes" style="color: var(--accent-blue); text-decoration: none; font-weight: 600;">
                                {{ $salesOrder->quote->quote_number }}
                            </a>
                        </span>
                    </p>
                    @endif
                    <p>
                        <strong>حالة الشحن:</strong>
                        @php
                            $statusLabel = match($salesOrder->status) {
                                'delivered' => 'تم التسليم للعميل',
                                'shipped' => 'تم الشحن للتسليم',
                                'cancelled' => 'تم الإلغاء',
                                default => 'قيد الانتظار والمعالجة'
                            };
                        @endphp
                        <span>{{ $statusLabel }}</span>
                    </p>
                </div>

                @if($salesOrder->shipping_address)
                <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--border-color);">
                    <h5 style="color: var(--text-medium); margin-bottom: 0.5rem;"><i class="fas fa-map-marker-alt"></i> عنوان الشحن والتوصيل</h5>
                    <p style="margin: 0; font-size: 0.875rem; color: var(--text-dark); background: var(--bg-hover); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        {{ $salesOrder->shipping_address }}
                    </p>
                </div>
                @endif
            </div>
        </div>

        <!-- Convert Action Card -->
        @if($salesOrder->status === 'confirmed')
        <div class="card" style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.1) 100%); border-color: rgba(16, 185, 129, 0.3); margin-bottom: 0;">
            <div class="card-body" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--success-dark); margin-bottom: 0.5rem;"><i class="fas fa-exchange-alt"></i> تحويل إلى فاتورة مبيعات</h4>
                <p class="text-muted" style="font-size: 0.85rem; margin-bottom: 1.25rem;">تم تأكيد هذا الطلب. يمكنك الآن تحويله إلى فاتورة مبيعات نظامية وتحديث الأرصدة والمخزون مباشرة.</p>
                <form action="{{ route('admin.sales-orders.convert-to-invoice', $salesOrder) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.5rem; font-weight: 700;">
                        <i class="fas fa-file-invoice-dollar"></i> تحويل لفاتورة مبيعات
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
