@extends('layouts.admin')

@section('title')
    S3 - {{ $s3->name }}: Details
@endsection

@section('content-header')
    <h1>{{ $s3->name }}<small>Edit details for this S3 configuration.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.buckets') }}">S3 Configurations</a></li>
        <li><a href="{{ route('admin.buckets.view', $s3->id) }}">{{ $s3->name }}</a></li>
        <li class="active">Details</li>
    </ol>
@endsection

@section('content')
@include('admin.s3.partials.navigation')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">S3 Configuration</h3>
            </div>
            <form action="{{ route('admin.buckets.view.details', $s3->id) }}" method="POST">
                <div class="box-body">
                    <div class="form-group">
                        <label for="name" class="control-label">Name <span class="field-required"></span></label>
                        <input type="text" name="name" value="{{ old('name', $s3->name) }}" class="form-control" required />
                        <p class="text-muted small">A display name for this S3 configuration.</p>
                    </div>
                    <div class="form-group">
                        <label for="description" class="control-label">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $s3->description) }}</textarea>
                        <p class="text-muted small">A brief description of this S3 configuration.</p>
                    </div>
                    <div class="form-group">
                        <label for="access_key" class="control-label">Access Key <span class="field-required"></span></label>
                        <input type="text" name="access_key" value="{{ old('access_key', $s3->access_key) }}" class="form-control" required />
                        <p class="text-muted small">The access key for the S3 service.</p>
                    </div>
                    <div class="form-group">
                        <label for="secret_key" class="control-label">Secret Key <span class="field-required"></span></label>
                        <input type="password" name="secret_key" value="{{ old('secret_key', $s3->secret_key) }}" class="form-control" required />
                        <p class="text-muted small">The secret key for the S3 service.</p>
                    </div>
                    <div class="form-group">
                        <label for="endpoint" class="control-label">Endpoint</label>
                        <input type="url" name="endpoint" value="{{ old('endpoint', $s3->endpoint) }}" class="form-control" />
                        <p class="text-muted small">The endpoint URL for the S3 service, make sure to include <code>https://</code>. Leave empty for AWS S3.</p>
                    </div>
                    <div class="form-group">
                        <label for="region" class="control-label">Region</label>
                        <input type="text" name="region" value="{{ old('region', $s3->region ?: 'us-east-1') }}" class="form-control" />
                        <p class="text-muted small">The region for the S3 service. For example, <code>us-west-004</code> on BackBlaze. Leave blank for us-east-1 on AWS</p>
                    </div>
                    <div class="form-group">
                        <label for="bucket_name" class="control-label">Bucket Name <span class="field-required"></span></label>
                        <input type="text" name="bucket_name" value="{{ old('bucket_name', $s3->bucket_name) }}" class="form-control" required />
                        <p class="text-muted small">The name of the S3 bucket.</p>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="hidden" name="use_path_style_endpoint" value="0" />
                            <input id="use_path_style_endpoint" name="use_path_style_endpoint" type="checkbox" value="1" {{ ((int) old('use_path_style_endpoint', $s3->use_path_style_endpoint ? 1 : 0)) ? 'checked' : '' }} />
                            <label for="use_path_style_endpoint" class="strong">Use Path Style Endpoints</label>
                        </div>
                        <p class="text-muted small">Enable this for some S3-compatible services that require path-style endpoints.</p>
                    </div>
                    <div class="form-group">
                        <div class="checkbox checkbox-primary no-margin-bottom">
                            <input type="hidden" name="enabled" value="0" />
                            <input id="enabled" name="enabled" type="checkbox" value="1" {{ ((int) old('enabled', $s3->enabled ? 1 : 0)) ? 'checked' : '' }} />
                            <label for="enabled" class="strong">Enabled</label>
                        </div>
                        <p class="text-muted small">Enable or disable this S3 configuration.</p>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="button" id="test-connection" class="btn btn-sm btn-info">
                        <i class="fa fa-refresh fa-spin" style="display: none;"></i> Test Connection
                    </button>
                    <button type="submit" class="btn btn-primary pull-right">Update Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('#test-connection').click(function () {
        const $button = $(this);
        const $spinner = $button.find('.fa-spin');

        $button.prop('disabled', true);
        $spinner.show();

        $.post('{{ route('admin.buckets.test-connection') }}', {
            _token: '{{ csrf_token() }}',
            access_key: $('input[name="access_key"]').val(),
            secret_key: $('input[name="secret_key"]').val(),
            endpoint: $('input[name="endpoint"]').val(),
            region: $('input[name="region"]').val(),
            bucket_name: $('input[name="bucket_name"]').val(),
            use_path_style_endpoint: $('#use_path_style_endpoint').is(':checked') ? '1' : '0',
        })
        .done(function (response) {
            swal({ type: 'success', title: 'Success', text: response.message });
        })
        .fail(function (xhr) {
            const response = xhr.responseJSON || {};
            const message = response.message || xhr.responseText || `Request failed with status ${xhr.status}.`;
            swal({ type: 'error', title: 'Connection Failed', text: message });
        })
        .always(function () {
            $button.prop('disabled', false);
            $spinner.hide();
        });
    });
    </script>
@endsection
