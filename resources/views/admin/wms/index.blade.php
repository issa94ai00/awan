@extends('admin.layout')

@section('title', __('messages.wms_dashboard'))

@section('content')
<div id="wms-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
