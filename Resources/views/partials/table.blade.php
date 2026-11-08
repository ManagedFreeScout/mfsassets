{{-- A compact asset table (organisation and customer tabs). --}}
<table class="table table-striped mfsassets-table">
    <thead><tr><th>{{ __('Tag') }}</th><th>{{ __('Name') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th>@if (!empty($show_owner))<th>{{ __('Owner') }}</th>@endif<th>{{ __('Assigned to') }}</th><th>{{ __('Location') }}</th></tr></thead>
    <tbody>
        @forelse ($assets as $a)
            <tr>
                <td><a href="{{ $a->url() }}" class="mfsassets-tag">{{ $a->tag }}</a></td>
                <td><a href="{{ $a->url() }}">{{ $a->name }}</a></td>
                <td>{{ $a->type ? $a->type->name : '' }}</td>
                <td><span class="mfsassets-status mfsassets-status-{{ $a->status }}">{{ $a->statusName() }}</span></td>
                @if (!empty($show_owner))<td>{{ $a->owner ? $a->owner->name : '' }}</td>@endif
                <td>{{ $a->assignedLabel() }}</td>
                <td>{{ $a->location ? $a->location->path() : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-help">{{ $empty ?? __('No assets.') }}</td></tr>
        @endforelse
    </tbody>
</table>
