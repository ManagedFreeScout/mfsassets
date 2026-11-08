{{-- Search filter "Asset" (card #426): conversations linked to the asset with this tag or serial number. --}}
<div class="col-sm-6 form-group @if (isset($filters['asset'])) active @endif" data-filter="asset">
    <label>{{ __('Asset') }} <b class="remove" data-toggle="tooltip" title="{{ __('Remove filter') }}">×</b></label>
    <input type="text" name="f[asset]" class="form-control" value="{{ $filters['asset'] ?? '' }}" placeholder="{{ __('Tag or serial number, e.g. AST-0001') }}" @if (empty($filters['asset'])) disabled @endif>
</div>
