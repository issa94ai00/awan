@extends('admin.layout')

@section('title', __('messages.entity_logs'))

@section('content')
<div id="audit-entity-logs"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
