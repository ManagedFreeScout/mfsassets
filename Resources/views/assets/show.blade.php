@extends('layouts.app')

@section('title', $asset->tag.' '.$asset->name.' - '.__('Assets'))

@section('content')
@php
    $S = \Modules\MFSAssets\Services\AssetService::class;
    $type = $asset->type;
    $values = $asset->values();
    $actions = ['created' => __('Created'), 'updated' => __('Changed'), 'assigned' => __('Changed hands'), 'moved' => __('Moved'), 'status_changed' => __('Status changed'), 'linked' => __('Linked to a conversation'), 'unlinked' => __('Unlinked from a conversation'), 'reported' => __('Reported via QR label')];
@endphp
<div class="container mfsassets-page">
    <div class="flexy-container">
        <div class="flexy-item">
            <span class="heading"><span class="mfsassets-tag">{{ $asset->tag }}</span> {{ $asset->name }}</span>
            <span class="mfsassets-status mfsassets-status-{{ $asset->status }}">{{ $asset->statusName() }}</span>
        </div>
        <div class="flexy-item margin-left">
            @if ($can_manage)<a href="{{ route('mfsassets.edit', ['id' => $asset->id]) }}" class="btn btn-bordered">{{ __('Edit') }}</a>@endif
            <a href="{{ route('mfsassets.label', ['id' => $asset->id]) }}" class="btn btn-link" target="_blank">{{ __('QR label') }}</a>
            <a href="{{ route('mfsassets.index') }}" class="btn btn-link">{{ __('All assets') }}</a>
        </div>
        <div class="flexy-block"></div>
    </div>
    @include('partials/flash_messages')

    <div class="row margin-top">
        <div class="col-md-6">
            <table class="table mfsassets-details">
                <tr><th>{{ __('Type') }}</th><td>{{ $type ? $type->name : '–' }}</td></tr>
                <tr><th>{{ __('Owner') }}</th><td>@if ($asset->owner)<a href="{{ $asset->owner->url() }}" class="mfsorg-badge" style="background:{{ $asset->owner->color }};color:{{ $asset->owner->textColor() }}">{{ $asset->owner->name }}</a>@endif</td></tr>
                <tr><th>{{ __('Assigned to') }}</th><td>{{ $asset->assignedLabel() }}</td></tr>
                <tr><th>{{ __('Location') }}</th><td>{{ $asset->location ? $asset->location->path() : __('None') }}</td></tr>
                <tr><th>{{ __('Serial number') }}</th><td>{{ $asset->serial ?: '–' }}</td></tr>
                <tr><th>{{ __('Supplier') }}</th><td>{{ $asset->supplier ?: '–' }}</td></tr>
                <tr><th>{{ __('Purchased') }}</th><td>{{ $asset->purchase_date ? $asset->purchase_date->format('j M Y') : '–' }}@if ($asset->purchase_price !== null) · {{ number_format($asset->purchase_price, 2, ',', '.') }}@endif</td></tr>
                <tr><th>{{ __('Warranty until') }}</th><td>@if ($asset->warranty_end)<span @if ($asset->warranty_end->isPast()) class="text-danger" @endif>{{ $asset->warranty_end->format('j M Y') }}</span>@else – @endif</td></tr>
                @if ($type)
                    @foreach ($type->fieldList() as $f)
                        @php $v = $values[$f['key']] ?? null; @endphp
                        <tr><th>{{ $f['label'] }}</th><td>@if ($f['type'] == 'checkbox'){{ $v ? __('Yes') : __('No') }}@else{{ ($v === null || $v === '') ? '–' : $v }}@endif</td></tr>
                    @endforeach
                @endif
                @if ($asset->notes)<tr><th>{{ __('Notes') }}</th><td class="mfsassets-pre">{{ $asset->notes }}</td></tr>@endif
            </table>

            <h4>{{ __('Files') }}</h4>
            <ul class="mfsassets-files">
                @forelse ($files as $f)
                    <li><a href="{{ route('mfsassets.download', ['id' => $asset->id, 'file_id' => $f->id]) }}">{{ $f->file_name }}</a> <small class="text-help">{{ round($f->size / 1024) }} KB</small>
                        @if ($can_manage)
                            <form method="POST" action="{{ route('mfsassets.deletefile', ['id' => $asset->id, 'file_id' => $f->id]) }}" class="inline-form">{{ csrf_field() }}<button type="submit" class="btn btn-link btn-xs" data-mfsassets-confirm="{{ __('Delete :file?', ['file' => $f->file_name]) }}">{{ __('Delete') }}</button></form>
                        @endif
                    </li>
                @empty
                    <li class="text-help">{{ __('No files, e.g. the invoice or warranty card.') }}</li>
                @endforelse
            </ul>
            @if ($can_manage)
                <form method="POST" action="{{ route('mfsassets.upload', ['id' => $asset->id]) }}" enctype="multipart/form-data" class="form-inline">
                    {{ csrf_field() }}
                    <input type="file" name="file" class="form-control input-sm" required>
                    <button type="submit" class="btn btn-default btn-sm">{{ __('Upload') }}</button>
                </form>
            @endif
        </div>
        <div class="col-md-6">
            <h4>{{ __('Conversations') }} <small>({{ count($conversations) }})</small></h4>
            <ul class="mfsassets-convs">
                @forelse ($conversations as $c)
                    <li><a href="{{ $c->url() }}">#{{ $c->number }} {{ $c->subject }}</a> <small class="text-help">{{ $c->getStatusName() }} · {{ \App\User::dateFormat($c->last_reply_at ?: $c->created_at, 'j M Y') }}</small></li>
                @empty
                    <li class="text-help">{{ __('Not linked to a conversation yet. Link it from the Assets block on a conversation.') }}</li>
                @endforelse
            </ul>

            <h4>{{ __('History') }}</h4>
            <ul class="mfsassets-history">
                @foreach ($history as $h)
                    <li>
                        <span class="text-help">{{ \App\User::dateFormat($h->created_at, 'j M Y H:i') }} · {{ $h->user ? $h->user->getFullName() : __('System') }}</span><br>
                        <strong>{{ $actions[$h->action] ?? $h->action }}</strong>
                        @foreach ($h->changeList() as $field => $change)
                            @if ($field == 'conversation')
                                · #{{ $change }}
                            @elseif ($field == 'tag')
                                · {{ $change }}
                            @elseif ($field == 'field_values')
                                · {{ __('Fields') }}
                            @elseif (is_array($change))
                                · {{ $S::fieldName($field) }}: {{ $S::displayValue($field, $change[0]) }} → {{ $S::displayValue($field, $change[1]) }}
                            @endif
                        @endforeach
                    </li>
                @endforeach
            </ul>
            @if ($is_admin)
                <form method="POST" action="{{ route('mfsassets.delete', ['id' => $asset->id]) }}" class="margin-top">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-link text-danger" data-mfsassets-confirm="{{ __('Delete :tag for good, with its history and files? To keep the history, set the status to Retired or Disposed instead.', ['tag' => $asset->tag]) }}">{{ __('Delete asset') }}</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection
