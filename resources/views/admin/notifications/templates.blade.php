@extends('admin.layout')

@section('title', __('messages.templates'))

@section('content')
<div id="notifications-templates"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
