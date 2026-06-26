@extends('admin.layout')

@section('title', __('messages.inventory_analytics'))

@section('content')
<div id="analytics-inventory"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
