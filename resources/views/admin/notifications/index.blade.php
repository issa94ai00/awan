@extends('admin.layout')

@section('title', __('messages.notifications'))

@section('content')
<div id="notifications-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
