@extends('admin.layout')

@section('title', 'تصدير التقارير')

@section('content')
<div class="page-header">
    <h1><i class="fas fa-file-export"></i> تصدير التقارير</h1>
    <p>جهز ملفات التصدير لكافة وحدات النظام.</p>
</div>

<div class="card">
    <div class="card-body">
        <ul class="module-actions">
            <li><a href="#">تصدير تقارير المبيعات</a></li>
            <li><a href="#">تصدير تقارير المشتريات</a></li>
            <li><a href="#">تصدير تقارير المخزون</a></li>
            <li><a href="#">تصدير تقارير الموظفين</a></li>
            <li><a href="#">تصدير تقارير CRM</a></li>
        </ul>
    </div>
</div>
@endsection
