{{-- Assets tab on the organisation page (card #426, v1 item 7). --}}
<div class="margin-top">
    @if ($can_manage)<a href="{{ route('mfsassets.create', ['owner_id' => $org->id]) }}" class="btn btn-bordered btn-sm">{{ __('New asset') }}</a>@endif
    <a href="{{ route('mfsassets.index', ['owner_id' => $org->id, 'all' => 1]) }}" class="btn btn-link btn-sm">{{ __('Open in the asset list (with filters and retired assets)') }}</a>
</div>
<h4 class="margin-top">{{ __('Owned by :name', ['name' => $org->name]) }}</h4>
<div class="table-responsive">@include('mfsassets::partials.table', ['assets' => $owned, 'empty' => __('No assets owned by this organisation.')])</div>
@if (count($here))
    <h4 class="margin-top">{{ __('At its locations, owned by others') }}</h4>
    <div class="table-responsive">@include('mfsassets::partials.table', ['assets' => $here, 'show_owner' => true])</div>
@endif
