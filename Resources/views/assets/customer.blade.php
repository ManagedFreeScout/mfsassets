@extends('layouts.app')

@section('title_full', $customer->getFullName().' - '.__('Assets'))
@section('body_class', 'sidebar-no-height')

@section('sidebar')
    <div class="profile-preview">
        @include('customers/profile_menu')
        @include('customers/profile_snippet')
    </div>
@endsection

@section('content')
    @include('customers/profile_tabs')
    <div class="container mfsassets-page">
        @if ($can_manage && count($emails))
            <div class="margin-top"><a href="{{ route('mfsassets.create', ['email' => $emails[0]]) }}" class="btn btn-bordered btn-sm">{{ __('New asset for :email', ['email' => $emails[0]]) }}</a>
                <a href="{{ route('mfsassets.export', ['email' => $emails[0], 'all' => 1]) }}" class="btn btn-link btn-sm">{{ __('Export CSV (e.g. for a data request)') }}</a></div>
        @endif
        <div class="table-responsive margin-top">
            @include('mfsassets::partials.table', ['assets' => $assets, 'show_owner' => true, 'empty' => __('No assets assigned to this customer\'s addresses.')])
        </div>
    </div>
@endsection
