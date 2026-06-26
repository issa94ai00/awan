@extends('admin.layout')

@section('title', __('messages.returns'))

@section('content')
<div id="rma-index"></div>
@endsection

@push('scripts')
<script src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
