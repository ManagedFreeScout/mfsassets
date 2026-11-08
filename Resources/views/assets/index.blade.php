@extends('layouts.app')

@section('title', __('Assets'))

@section('content')
<div class="container mfsassets-page">
    <div class="flexy-container">
        <div class="flexy-item">
            <span class="heading">{{ __('Assets') }} <small>({{ $assets->total() }})</small></span>
        </div>
        @if ($can_manage)
            <div class="flexy-item margin-left">
                <a href="{{ route('mfsassets.create') }}" class="btn btn-bordered">{{ __('New asset') }}</a>
                @if (Auth::user()->isAdmin())<a href="{{ route('mfsassets.types') }}" class="btn btn-link">{{ __('Types') }}</a>@endif
                <a href="#" class="btn btn-link" data-toggle="collapse" data-target="#mfsassets-import">{{ __('Import CSV') }}</a>
            </div>
        @endif
        <div class="flexy-item margin-left"><a href="{{ route('mfsassets.export', request()->query()) }}" class="btn btn-link">{{ __('Export CSV') }}</a></div>
        <div class="flexy-block"></div>
    </div>

    @include('partials/flash_messages')

    @if ($can_manage)
        <div id="mfsassets-import" class="collapse well margin-top">
            <form method="POST" action="{{ route('mfsassets.import') }}" enctype="multipart/form-data" class="form-inline">
                {{ csrf_field() }}
                <input type="file" name="csv" accept=".csv,text/csv" class="form-control" required>
                <button type="submit" class="btn btn-primary">{{ __('Import') }}</button>
                <p class="help-block">{{ __('First line: column names, as in the export. Only name is required. tag: an existing tag updates that asset, empty creates a new one. type: an existing type name. owner: organisation name (empty = our organisation). assigned_email, assigned_department (name), location (path as shown, e.g. "Head office › Building A"), status (ordered, in_stock, in_use, in_repair, retired, disposed), serial, supplier, purchase_date, purchase_price, warranty_end, notes. Commas and semicolons both work.') }}</p>
            </form>
        </div>
    @endif

    <form method="GET" action="{{ route('mfsassets.index') }}" class="form-inline margin-top mfsassets-filters">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Tag, name, serial number or user') }}">
        <select name="type_id" class="form-control">
            <option value="">{{ __('All types') }}</option>
            @foreach ($types as $t)
                <option value="{{ $t->id }}" @if (request('type_id') == $t->id) selected @endif>{{ $t->name }}</option>
            @endforeach
        </select>
        <select name="owner_id" class="form-control">
            <option value="">{{ __('All owners') }}</option>
            @foreach ($orgs as $o)
                <option value="{{ $o->id }}" @if (request('owner_id') == $o->id) selected @endif>{{ $o->name }}</option>
            @endforeach
        </select>
        <select name="status" class="form-control">
            <option value="">{{ __('All except retired and disposed') }}</option>
            @foreach ($statuses as $key => $label)
                <option value="{{ $key }}" @if (request('status') == $key) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-default">{{ __('Filter') }}</button>
    </form>

    @php
        $sortLink = function ($key, $label) use ($sort, $dir) {
            $newDir = ($sort == $key && $dir == 'asc') ? 'desc' : 'asc';
            $arrow = $sort == $key ? ($dir == 'asc' ? ' ▲' : ' ▼') : '';
            return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $key, 'dir' => $newDir, 'page' => 1])).'">'.e($label).$arrow.'</a>';
        };
    @endphp
    <div class="table-responsive margin-top">
        <table class="table table-striped mfsassets-table">
            <thead>
                <tr>
                    <th>{!! $sortLink('tag', __('Tag')) !!}</th>
                    <th>{!! $sortLink('name', __('Name')) !!}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{!! $sortLink('status', __('Status')) !!}</th>
                    <th>{{ __('Owner') }}</th>
                    <th>{{ __('Assigned to') }}</th>
                    <th>{{ __('Location') }}</th>
                    <th>{!! $sortLink('warranty_end', __('Warranty until')) !!}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assets as $a)
                    <tr>
                        <td><a href="{{ $a->url() }}" class="mfsassets-tag">{{ $a->tag }}</a></td>
                        <td><a href="{{ $a->url() }}">{{ $a->name }}</a>@if ($a->serial)<div class="text-help">{{ $a->serial }}</div>@endif</td>
                        <td>{{ $a->type ? $a->type->name : '' }}</td>
                        <td><span class="mfsassets-status mfsassets-status-{{ $a->status }}">{{ $a->statusName() }}</span></td>
                        <td>@if ($a->owner)<span class="mfsorg-badge" style="background:{{ $a->owner->color }};color:{{ $a->owner->textColor() }}">{{ $a->owner->name }}</span>@endif</td>
                        <td>{{ $a->assignedLabel() }}</td>
                        <td>{{ $a->location ? $a->location->path() : '' }}</td>
                        <td>@if ($a->warranty_end)<span @if ($a->warranty_end->isPast()) class="text-danger" @endif>{{ $a->warranty_end->format('j M Y') }}</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-help">{{ __('No assets yet.') }} @if ($can_manage)<a href="{{ route('mfsassets.create') }}">{{ __('Add the first one') }}</a>@endif</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $assets->links() }}
</div>
@endsection
