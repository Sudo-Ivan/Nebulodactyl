@extends('layouts.admin')

@section('title')
    S3 - {{ $s3->name }}: Delete
@endsection

@section('content-header')
    <h1>{{ $s3->name }}<small>Delete this S3 configuration.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.buckets') }}">S3 Configurations</a></li>
        <li><a href="{{ route('admin.buckets.view', $s3->id) }}">{{ $s3->name }}</a></li>
        <li class="active">Delete</li>
    </ol>
@endsection

@section('content')
@include('admin.s3.partials.navigation')
<div class="row">
    <div class="col-md-6">
        <div class="box box-danger">
            <div class="box-header with-border">
                <h3 class="box-title">Delete S3 Configuration</h3>
            </div>
            <div class="box-body">
                <p>This action will permanently delete this S3 bucket configuration.</p>
                @if($s3->servers_count > 0)
                    <div class="callout callout-danger">
                        <p><strong>{{ $s3->servers_count }} server(s)</strong> are currently using this S3 configuration. You must reassign them to another bucket before deleting.</p>
                    </div>
                @else
                    <p class="text-danger small">Deleting an S3 configuration is irreversible. Any backups stored in this bucket will become inaccessible from the panel.</p>
                @endif
            </div>
            <div class="box-footer">
                <form id="deleteform" action="{{ route('admin.buckets.view.delete', $s3->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button id="deletebtn" class="btn btn-danger" {{ $s3->servers_count > 0 ? 'disabled' : '' }}>
                        Delete This Configuration
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    @if($s3->servers_count === 0)
    <script>
    $('#deletebtn').click(function (event) {
        event.preventDefault();
        swal({
            title: '',
            type: 'warning',
            text: 'Are you sure that you want to delete this S3 configuration? There is no going back.',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#d9534f',
            closeOnConfirm: false
        }, function () {
            $('#deleteform').submit()
        });
    });
    </script>
    @endif
@endsection
