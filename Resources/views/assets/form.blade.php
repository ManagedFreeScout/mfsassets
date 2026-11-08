@extends('layouts.app')

@section('title', $asset->exists ? $asset->tag.' - '.__('Assets') : __('New asset'))

@section('content')
@php
    $mode = old('assigned_mode', $asset->assigned_email ? 'email' : ($asset->assigned_department_id ? 'department' : 'none'));
    $type = $asset->type_id ? $types->firstWhere('id', $asset->type_id) : null;
    $values = $asset->values();
@endphp
<div class="container mfsassets-page">
    <div class="section-heading">{{ $asset->exists ? __('Edit :tag', ['tag' => $asset->tag]) : __('New asset') }}</div>
    @include('partials/flash_messages')
    <form method="POST" action="{{ $asset->exists ? route('mfsassets.update', ['id' => $asset->id]) : route('mfsassets.store') }}" class="form-horizontal margin-top mfsassets-form" data-options-url="{{ route('mfsassets.options') }}">
        {{ csrf_field() }}
        @if ($conversation_id)<input type="hidden" name="conversation_id" value="{{ $conversation_id }}">@endif

        <div class="form-group{{ $errors->has('name') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{{ __('Name') }}</label>
            <div class="col-sm-6"><input type="text" name="name" class="form-control" maxlength="191" required value="{{ old('name', $asset->name) }}" placeholder="{{ __('e.g. Laptop Julia') }}">@include('partials/field_error', ['field' => 'name'])</div>
        </div>
        <div class="form-group">
            <label class="col-sm-2 control-label">{{ __('Type') }}</label>
            <div class="col-sm-4">
                <select name="type_id" class="form-control mfsassets-type">
                    <option value="">–</option>
                    @foreach ($types as $t)<option value="{{ $t->id }}" @if (old('type_id', $asset->type_id) == $t->id) selected @endif>{{ $t->name }}</option>@endforeach
                </select>
                <p class="help-block">{{ __('Changing the type shows its own fields after saving.') }}</p>
            </div>
            <label class="col-sm-1 control-label">{{ __('Status') }}</label>
            <div class="col-sm-3">
                <select name="status" class="form-control">
                    @foreach ($statuses as $key => $label)<option value="{{ $key }}" @if (old('status', $asset->status) == $key) selected @endif>{{ $label }}</option>@endforeach
                </select>
            </div>
        </div>

        <h4 class="mfsassets-subhead">{{ __('Whose is it, who uses it, where is it?') }}</h4>
        <div class="form-group{{ $errors->has('owner_id') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{{ __('Owner') }}</label>
            <div class="col-sm-6">
                <select name="owner_id" class="form-control mfsassets-owner" required>
                    <option value="">–</option>
                    @foreach ($orgs as $o)<option value="{{ $o->id }}" @if (old('owner_id', $asset->owner_id) == $o->id) selected @endif>{{ $o->name }}</option>@endforeach
                </select>
                @include('partials/field_error', ['field' => 'owner_id'])
            </div>
        </div>
        <div class="form-group{{ $errors->has('assigned_to') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{{ __('Assigned to') }}</label>
            <div class="col-sm-8">
                <label class="radio-inline"><input type="radio" name="assigned_mode" value="email" @if ($mode == 'email') checked @endif> {{ __('A person') }}</label>
                <label class="radio-inline"><input type="radio" name="assigned_mode" value="department" @if ($mode == 'department') checked @endif> {{ __('A department') }}</label>
                <label class="radio-inline"><input type="radio" name="assigned_mode" value="none" @if ($mode == 'none') checked @endif> {{ __('Nobody (shared)') }}</label>
                <div class="mfsassets-assign mfsassets-assign-email margin-top-10" @if ($mode != 'email') style="display:none" @endif>
                    <input type="email" name="assigned_email" class="form-control" value="{{ old('assigned_email', $asset->assigned_email) }}" placeholder="{{ __('E-mail address of the person') }}">
                </div>
                <div class="mfsassets-assign mfsassets-assign-department margin-top-10" @if ($mode != 'department') style="display:none" @endif>
                    <select name="assigned_department_id" class="form-control mfsassets-departments">
                        <option value="">–</option>
                        @foreach ($departments as $id => $name)<option value="{{ $id }}" @if (old('assigned_department_id', $asset->assigned_department_id) == $id) selected @endif>{{ $name }}</option>@endforeach
                    </select>
                </div>
                @include('partials/field_error', ['field' => 'assigned_to'])
            </div>
        </div>
        <div class="form-group{{ $errors->has('location_id') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{{ __('Location') }}</label>
            <div class="col-sm-6">
                <select name="location_id" class="form-control mfsassets-locations">
                    <option value="">{{ __('None (e.g. digital)') }}</option>
                    @foreach ($locations as $org_name => $tree)
                        <optgroup label="{{ $org_name }}">
                            @foreach ($tree as $id => $path)<option value="{{ $id }}" @if (old('location_id', $asset->location_id) == $id) selected @endif>{{ $path }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
                <p class="help-block">{{ __('Locations are kept per organisation in MFS Organisations (Locations tab).') }}</p>
            </div>
        </div>

        <h4 class="mfsassets-subhead">{{ __('Details') }}</h4>
        <div class="form-group">
            <label class="col-sm-2 control-label">{{ __('Serial number') }}</label>
            <div class="col-sm-4"><input type="text" name="serial" class="form-control" maxlength="191" value="{{ old('serial', $asset->serial) }}"></div>
            <label class="col-sm-1 control-label">{{ __('Supplier') }}</label>
            <div class="col-sm-3"><input type="text" name="supplier" class="form-control" maxlength="191" value="{{ old('supplier', $asset->supplier) }}"></div>
        </div>
        <div class="form-group{{ $errors->has('purchase_date') || $errors->has('purchase_price') || $errors->has('warranty_end') ? ' has-error' : '' }}">
            <label class="col-sm-2 control-label">{{ __('Purchased') }}</label>
            <div class="col-sm-2"><input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', $asset->purchase_date ? $asset->purchase_date->toDateString() : '') }}" aria-label="{{ __('Purchase date') }}"></div>
            <div class="col-sm-2"><input type="text" name="purchase_price" class="form-control" value="{{ old('purchase_price', $asset->purchase_price) }}" placeholder="{{ __('Price') }}" aria-label="{{ __('Purchase price') }}"></div>
            <label class="col-sm-2 control-label">{{ __('Warranty until') }}</label>
            <div class="col-sm-2"><input type="date" name="warranty_end" class="form-control" value="{{ old('warranty_end', $asset->warranty_end ? $asset->warranty_end->toDateString() : '') }}"></div>
        </div>
        @if ($type && count($type->fieldList()))
            <h4 class="mfsassets-subhead">{{ $type->name }}</h4>
            @foreach ($type->fieldList() as $f)
                @php $v = old('fields.'.$f['key'], $values[$f['key']] ?? null); @endphp
                <div class="form-group">
                    <label class="col-sm-2 control-label">{{ $f['label'] }}</label>
                    <div class="col-sm-6">
                        @if ($f['type'] == 'select')
                            <select name="fields[{{ $f['key'] }}]" class="form-control"><option value="">–</option>@foreach ($f['options'] ?? [] as $opt)<option value="{{ $opt }}" @if ($v === $opt) selected @endif>{{ $opt }}</option>@endforeach</select>
                        @elseif ($f['type'] == 'checkbox')
                            <input type="hidden" name="fields[{{ $f['key'] }}]" value="0"><label class="checkbox-inline"><input type="checkbox" name="fields[{{ $f['key'] }}]" value="1" @if ($v) checked @endif> {{ __('Yes') }}</label>
                        @else
                            <input type="{{ $f['type'] == 'date' ? 'date' : 'text' }}" name="fields[{{ $f['key'] }}]" class="form-control" value="{{ $v }}" @if ($f['type'] == 'number') inputmode="decimal" @endif>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
        <div class="form-group">
            <label class="col-sm-2 control-label">{{ __('Notes') }}</label>
            <div class="col-sm-8"><textarea name="notes" class="form-control" rows="3">{{ old('notes', $asset->notes) }}</textarea></div>
        </div>
        <div class="form-group">
            <div class="col-sm-8 col-sm-offset-2">
                <button type="submit" class="btn btn-primary">{{ $asset->exists ? __('Save') : __('Create asset') }}</button>
                <a href="{{ $asset->exists ? $asset->url() : route('mfsassets.index') }}" class="btn btn-link">{{ __('Cancel') }}</a>
            </div>
        </div>
    </form>
</div>
@endsection
