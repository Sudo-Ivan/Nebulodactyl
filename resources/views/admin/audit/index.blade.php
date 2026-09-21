@extends('layouts.admin')

@section('title')
    Audit Log
@endsection

@section('content-header')
    <h1>Audit Log<small>Every change made through the admin area, newest first.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Audit Log</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Admin Activity</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Admin</th>
                            <th>Event</th>
                            <th>Path</th>
                            <th class="text-center">Status</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="text-nowrap" title="{{ $entry->timestamp->toIso8601String() }}">{{ $entry->timestamp->diffForHumans() }}</td>
                                <td>
                                    @if ($entry->actor)
                                        {{ $entry->actor->name_first ?? $entry->actor->username }}
                                    @else
                                        <span class="text-muted">system</span>
                                    @endif
                                </td>
                                <td><code>{{ $entry->event }}</code></td>
                                <td><code class="text-muted">{{ $entry->properties->get('method', '') }} /{{ $entry->properties->get('uri', '') }}</code></td>
                                @php $status = $entry->properties->get('status'); @endphp
                                <td class="text-center">
                                    <span class="label {{ $status < 400 ? 'label-success' : 'label-danger' }}">{{ $status ?? '?' }}</span>
                                </td>
                                <td class="text-muted">{{ $entry->ip }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">No admin actions recorded yet. Entries appear after the next change.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($entries->hasPages())
                <div class="box-footer">
                    {{ $entries->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
