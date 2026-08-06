@extends('admin.layout')

@section('title', __('messages.wms_performance'))

@section('content')
<div id="wms-performance"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
