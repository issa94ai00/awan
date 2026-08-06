@extends('admin.layout')

@section('title', __('messages.packing_lists'))

@section('content')
<div id="wms-packing-form"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
