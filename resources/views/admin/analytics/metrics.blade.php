@extends('admin.layout')

@section('title', __('messages.custom_metrics'))

@section('content')
<div id="analytics-metrics"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
