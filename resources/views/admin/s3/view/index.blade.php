@extends('layouts.admin')

@section('title')
    S3 - {{ $s3->name }}
@endsection

@section('content-header')
    <h1>{{ $s3->name }}<small>{{ str_limit($s3->description ?? '', 60) }}</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.buckets') }}">S3 Configurations</a></li>
        <li class="active">{{ $s3->name }}</li>
    </ol>
@endsection

@section('content')
@include('admin.s3.partials.navigation')

<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Information</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <td>ID</td>
                        <td><code>{{ $s3->id }}</code></td>
                    </tr>
                    <tr>
                        <td>Name</td>
                        <td>{{ $s3->name }}</td>
                    </tr>
                    <tr>
                        <td>Description</td>
                        <td>{!! $s3->description ?? '<span class="label label-default">None</span>' !!}</td>
                    </tr>
                    <tr>
                        <td>Bucket Name</td>
                        <td><code>{{ $s3->bucket_name }}</code></td>
                    </tr>
                    <tr>
                        <td>Endpoint</td>
                        <td>
                            @if($s3->endpoint)
                                <code>{{ $s3->endpoint }}</code>
                            @else
                                <span class="label label-default">Default (AWS)</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td>Region</td><td><code>{{ $s3->region ?: 'us-east-1' }}</code></td></tr>
                    <tr>
                        <td>Path Style Endpoints</td>
                        <td>
                            @if($s3->use_path_style_endpoint)
                                <span class="label label-success">Enabled</span>
                            @else
                                <span class="label label-default">Disabled</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Status</td>
                        <td>
                            @if($s3->enabled)
                                <span class="label label-success">Enabled</span>
                            @else
                                <span class="label label-danger">Disabled</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td>Created</td>
                        <td>{{ $s3->created_at->diffForHumans() }}</td>
                    </tr>
                    <tr>
                        <td>Updated</td>
                        <td>{{ $s3->updated_at->diffForHumans() }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-sm-4">
        <div class="box box-primary">
            <div class="box-body">
                <div class="small-box bg-zinc">
                    <div class="inner">
                        <h3>{{ $s3->servers_count ?? $s3->servers->count() }}</h3>
                        <p>Attached Servers</p>
                    </div>
                    <div class="icon"><i class="fa fa-server"></i></div>
                    <a href="{{ route('admin.buckets.view.servers', $s3->id) }}" class="small-box-footer">
                        View Servers <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>

                <div class="small-box bg-zinc">
                    <div class="inner">
                        <h3>{{ humanizeSize($storageUsed) }}</h3>
                        <p>Estimated Storage Usage</p>
                    </div>
                    <div class="icon"><i class="fa fa-cloud"></i></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
