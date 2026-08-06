@extends('admin.layout')

@section('title', __('messages.preferences'))

@section('content')
<div id="notifications-preferences"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
