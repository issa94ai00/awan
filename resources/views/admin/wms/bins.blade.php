@extends('admin.layout')

@section('title', __('messages.bins'))

@section('content')
<div id="wms-bins"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
