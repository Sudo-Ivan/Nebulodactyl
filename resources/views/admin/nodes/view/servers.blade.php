@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Servers
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>All servers currently assigned to this node.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Servers</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Process Manager</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tr>
                        <th>ID</th>
                        <th>Server Name</th>
                        <th>Owner</th>
                        <th>Service</th>
                    </tr>
                    @foreach($servers as $server)
                        <tr data-server="{{ $server->uuid }}">
                            <td><code>{{ $server->uuidShort }}</code></td>
                            <td><a href="{{ route('admin.servers.view', $server->id) }}">{{ $server->name }}</a></td>
                            <td><a href="{{ route('admin.users.view', $server->owner_id) }}">{{ $server->user->username }} ({{ $server->user->email }})</a></td>
                            <td>{{ $server->nest->name }} ({{ $server->egg->name }})</td>
                        </tr>
                    @endforeach
                </table>
                @if($servers->hasPages())
                    <div class="box-footer with-border">
                        <div class="col-md-12 text-center">{!! $servers->render() !!}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-6">
        <div class="box {{ $node->draining ? 'box-warning' : 'box-default' }}">
            <div class="box-header with-border">
                <h3 class="box-title">Drain Node</h3>
            </div>
            <div class="box-body">
                @if($node->draining)
                    <p class="text-warning"><strong>This node is draining.</strong> New deployments are blocked and its servers are being moved away.</p>
                @else
                    <p>Draining marks this node so no new deployments land on it, then starts a transfer for every server to the target node using automatically selected allocations. Servers that cannot be transferred are listed afterwards.</p>
                @endif
            </div>
            <div class="box-footer">
                @if($targets->isEmpty())
                    <p class="text-muted no-margin">Draining requires at least one other node.</p>
                @else
                    <form action="{{ route('admin.nodes.view.drain', $node->id) }}" method="POST" onsubmit="return confirm('Drain {{ addslashes($node->name) }} and transfer its servers to the selected node?');">
                        {!! csrf_field() !!}
                        <input type="hidden" name="node_id" value="{{ $node->id }}" />
                        <div class="input-group">
                            <select name="target_node_id" class="form-control">
                                @foreach($targets as $target)
                                    <option value="{{ $target->id }}">{{ $target->name }} ({{ $target->fqdn }})</option>
                                @endforeach
                            </select>
                            <span class="input-group-btn">
                                <button type="submit" class="btn btn-warning">Drain to this node</button>
                            </span>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
