{{-- Assets block on a conversation (card #426, v1 item 6), under the Organisations block. --}}
@php
    $groupNames = ['own' => __('Of this person'), 'department' => __('Of their department'), 'shared' => __('Shared in the organisation')];
    $linkForm = function ($asset, $action) use ($conversation) {
        return '<form method="POST" action="'.route('mfsassets.link', ['id' => $asset->id]).'" class="inline-form mfsassets-link-form">'.csrf_field()
            .'<input type="hidden" name="conversation_id" value="'.$conversation->id.'"><input type="hidden" name="action" value="'.$action.'">'
            .'<button type="submit" class="btn btn-link btn-xs" title="'.e($action == 'link' ? __('Link to this conversation') : __('Unlink')).'">'.($action == 'link' ? e(__('Link')) : '&times;').'</button></form>';
    };
@endphp
<div class="conv-sidebar-block mfsassets-conv-block">
    <div class="mfsassets-conv-title">{{ __('Assets') }}</div>
    @if (count($linked))
        <ul class="mfsassets-conv-list">
            @foreach ($linked as $a)
                <li><a href="{{ $a->url() }}" class="mfsassets-tag">{{ $a->tag }}</a> {{ $a->name }} {!! $linkForm($a, 'unlink') !!}</li>
            @endforeach
        </ul>
    @else
        <div class="text-help">{{ __('No assets linked') }}</div>
    @endif

    @foreach ($groups as $key => $assets)
        @if (count($assets))
            <div class="mfsassets-conv-sub">{{ $groupNames[$key] }}</div>
            <ul class="mfsassets-conv-list mfsassets-conv-suggest">
                @foreach ($assets as $a)
                    <li><a href="{{ $a->url() }}" class="mfsassets-tag">{{ $a->tag }}</a> {{ $a->name }} {!! $linkForm($a, 'link') !!}</li>
                @endforeach
            </ul>
        @endif
    @endforeach

    <form method="POST" action="{{ route('mfsassets.link', ['id' => 0]) }}" class="mfsassets-search-form" data-action-base="{{ url('/assets') }}">
        {{ csrf_field() }}
        <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
        <input type="hidden" name="action" value="link">
        <select class="form-control input-sm mfsassets-search" data-url="{{ route('mfsassets.search') }}" data-placeholder="{{ __('Find by tag, name or serial…') }}"></select>
    </form>
    @if ($can_manage)
        <a href="{{ route('mfsassets.create', array_filter(['conversation_id' => $conversation->id, 'owner_id' => $org_id ?: null, 'email' => $email ?: null])) }}" class="mfsassets-new-link text-help">{{ __('New asset for this conversation') }}</a>
    @endif
</div>
