@php
    $P = \Modules\MFSAssets\Providers\MFSAssetsServiceProvider::class;
    $orgsAvailable = $P::organisationsAvailable();
@endphp

<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-info">
            <strong>{{ __('MFS Assets') }}</strong> &mdash; {{ __('Register devices and other assets per organisation: whose it is, who uses it and where it is, and link them to conversations.') }}
            @if ($P::enabled())<a href="{{ route('mfsassets.index') }}">{{ __('Go to the assets') }}</a>@endif
        </div>
        @if (!$orgsAvailable)
            <div class="alert alert-warning">{{ __('MFS Assets needs MFS Organisations 1.1 or newer (with locations). Install and activate it first.') }}</div>
        @endif
    </div>
</div>

@if (config('mfsassets.enforce_license'))
    @include('mfsassets::settings.partials.license')
@endif

@if ($orgsAvailable)
<form class="form-horizontal" method="POST" action="{{ route('mfsassets.settings.save') }}">
    {{ csrf_field() }}
    <div class="row">
        <div class="col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading">{{ __('Assets') }}</div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ __('Our organisation') }}</label>
                        <div class="col-sm-6">
                            <select name="our_org" class="form-control">
                                <option value="0">–</option>
                                @foreach (\Modules\MFSOrganisations\Entities\Organisation::orderBy('name')->get(['id', 'name']) as $org)
                                    <option value="{{ $org->id }}" @if ($P::ourOrgId() == $org->id) selected @endif>{{ $org->name }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">{{ __('Your own company in MFS Organisations. New assets get it as owner, unless you choose a client.') }}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ __('Asset tag prefix') }}</label>
                        <div class="col-sm-3">
                            <input type="text" name="tag_prefix" class="form-control" maxlength="20" value="{{ \Modules\MFSAssets\Entities\Asset::tagPrefix() }}">
                            <p class="help-block">{{ __('New assets are numbered :example, :example2 and so on.', ['example' => \Modules\MFSAssets\Entities\Asset::tagPrefix().'0001', 'example2' => \Modules\MFSAssets\Entities\Asset::tagPrefix().'0002']) }}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-sm-3 control-label">{{ __('Report form mailbox') }}</label>
                        <div class="col-sm-6">
                            <select name="report_mailbox" class="form-control">
                                <option value="0">{{ __('Off: QR labels only open the asset page for agents') }}</option>
                                @foreach (\App\Mailbox::orderBy('name')->get(['id', 'name']) as $mailbox)
                                    <option value="{{ $mailbox->id }}" @if ((int) \Option::get($P::OPTION_REPORT_MAILBOX, 0) == $mailbox->id) selected @endif>{{ $mailbox->name }}</option>
                                @endforeach
                            </select>
                            <p class="help-block">{{ __('Someone who scans an asset\'s QR label without being logged in can report a problem; the report becomes a conversation in this mailbox, linked to the asset.') }}</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-6 col-sm-offset-3">
                            <a href="{{ route('mfsassets.types') }}">{{ __('Asset types and their fields') }} →</a>
                        </div>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </div>
</form>
@endif
