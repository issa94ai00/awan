@extends('admin.layout')

@section('title', __('messages.dashboards'))

@section('content')
<div id="analytics-dashboards"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
