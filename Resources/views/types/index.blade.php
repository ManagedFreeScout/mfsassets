@extends('layouts.app')

@section('title', __('Asset types'))

@section('content')
<div class="container mfsassets-page">
    <div class="flexy-container">
        <div class="flexy-item"><span class="heading">{{ __('Asset types') }}</span></div>
        <div class="flexy-item margin-left"><a href="{{ route('mfsassets.index') }}" class="btn btn-link">{{ __('All assets') }}</a></div>
        <div class="flexy-block"></div>
    </div>
    @include('partials/flash_messages')
    <p class="text-help margin-top">{{ __('Each type can have its own fields, one per line: Label | type | options. Types: :types. Options only for a list, separated by commas. Example: "RAM | select | 8 GB, 16 GB, 32 GB".', ['types' => implode(', ', array_keys($field_types))]) }}</p>

    @foreach ($types as $t)
        <form method="POST" action="{{ route('mfsassets.types.save') }}" class="well mfsassets-type-form">
            {{ csrf_field() }}
            <input type="hidden" name="type_id" value="{{ $t->id }}">
            <div class="form-inline">
                <input type="text" name="name" class="form-control" value="{{ $t->name }}" maxlength="191" required aria-label="{{ __('Name') }}">
                <span class="text-help">{{ trans_choice('{0} no assets|{1} 1 asset|[2,*] :n assets', $counts[$t->id] ?? 0, ['n' => $counts[$t->id] ?? 0]) }}</span>
            </div>
            <textarea name="fields" class="form-control margin-top-10" rows="{{ max(2, count($t->fieldList()) + 1) }}" placeholder="{{ __('Fields, one per line') }}">{{ \Modules\MFSAssets\Entities\AssetType::fieldsText($t->fieldList()) }}</textarea>
            <div class="margin-top-10">
                <button type="submit" name="action" value="update" class="btn btn-default btn-sm">{{ __('Save') }}</button>
                @if (empty($counts[$t->id]))<button type="submit" name="action" value="delete" class="btn btn-link btn-sm" data-mfsassets-confirm="{{ __('Delete type :name?', ['name' => $t->name]) }}">{{ __('Delete') }}</button>@endif
            </div>
        </form>
    @endforeach

    <form method="POST" action="{{ route('mfsassets.types.save') }}" class="well mfsassets-type-form">
        {{ csrf_field() }}
        <input type="hidden" name="action" value="add">
        <h4>{{ __('New type') }}</h4>
        <input type="text" name="name" class="form-control" maxlength="191" required placeholder="{{ __('e.g. Laptop') }}">
        <textarea name="fields" class="form-control margin-top-10" rows="3" placeholder="{{ __('Fields, one per line, e.g.') }}&#10;RAM | select | 8 GB, 16 GB, 32 GB&#10;Operating system | text&#10;Encrypted | checkbox"></textarea>
        <button type="submit" class="btn btn-primary btn-sm margin-top-10">{{ __('Add type') }}</button>
    </form>
</div>
@endsection
