@extends('admin.layout')

@section('title', __('messages.sales_analytics'))

@section('content')
<div id="analytics-sales"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
