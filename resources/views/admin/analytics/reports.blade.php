@extends('admin.layout')

@section('title', __('messages.reports'))

@section('content')
<div id="analytics-reports"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
