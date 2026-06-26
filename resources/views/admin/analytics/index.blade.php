@extends('admin.layout')

@section('title', __('messages.analytics_dashboard'))

@section('content')
<div id="analytics-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
