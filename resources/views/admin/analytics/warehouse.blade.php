@extends('admin.layout')

@section('title', __('messages.warehouse_analytics'))

@section('content')
<div id="analytics-warehouse"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
