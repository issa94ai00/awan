@extends('admin.layout')

@section('title', __('messages.warehouses'))

@section('content')
<div id="wms-warehouses"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
