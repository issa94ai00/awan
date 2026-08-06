@extends('admin.layout')

@section('title', __('messages.audit_logs'))

@section('content')
<div id="audit-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
