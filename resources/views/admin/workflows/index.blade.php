@extends('admin.layout')

@section('title', __('messages.workflows'))

@section('content')
<div id="workflows-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
