@extends('admin.layout')

@section('title', __('messages.cycle_counts'))

@section('content')
<div id="wms-cycle-count-form"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
