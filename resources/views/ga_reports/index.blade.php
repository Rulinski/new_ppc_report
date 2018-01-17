@extends('layouts.app')

@push('stylesheet')
<link rel="stylesheet" type="text/css" href="//cdn.datatables.net/1.10.15/css/jquery.dataTables.css">
@endpush

@section('content')
    <table class="table table-bordered" id="users-table">
        <thead>
        <tr>
            <th>Id</th>
            <th>Post Name</th>
            <th>Post URL</th>
            <th>Clusters</th>
            <th>GA Report111</th>
        </tr>
        </thead>
    </table>
@stop

@push('scripts')
<script type="text/javascript" charset="utf8" src="//cdn.datatables.net/1.10.15/js/jquery.dataTables.js"></script>
{{--<script>--}}
    {{--$(function() {--}}
        {{--$('#users-table').DataTable({--}}
            {{--processing: true,--}}
            {{--serverSide: true,--}}
            {{--ajax: '{!! route('datatables') !!}',--}}
            {{--columns: [--}}
                {{--{ data: 'id', name: 'id' },--}}
                {{--{ data: 'name', name: 'name' },--}}
                {{--{ data: 'email', name: 'email' },--}}
                {{--{ data: 'created_at', name: 'created_at' },--}}
                {{--{ data: 'updated_at', name: 'updated_at' }--}}
            {{--]--}}
        {{--});--}}
    {{--});--}}
{{--</script>--}}
@endpush
