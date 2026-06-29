@extends('admin.layout')

@section('title', __('messages.returns'))

@section('content')
<div id="rma-form"></div>
@endsection

@push('scripts')
<script type="module" src="{{ asset('build/assets/admin-mount.js') }}"></script>
@endpush
