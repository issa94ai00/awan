@extends('admin.layout')

@section('title', __('messages.financial_analytics'))

@section('content')
<div id="analytics-financial"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
