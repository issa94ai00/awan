@extends('admin.layout')

@section('title', __('messages.picking_lists'))

@section('content')
<div id="wms-picking-form"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
