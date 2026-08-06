@extends('admin.layout')

@section('title', __('messages.audit_statistics'))

@section('content')
<div id="audit-statistics"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
