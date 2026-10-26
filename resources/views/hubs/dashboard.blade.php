@extends('layouts.app')

@section('content')
@php
    $reportableChannels = ['online', 'wholesale', 'walk_in'];
    $currentUser = auth()->user();
    $assignedReportChannels = collect($currentUser?->sales_channels ?? [])
        ->map(fn ($channel) => strtolower(str_replace(['-', ' '], '_', (string) $channel)))
        ->filter(fn ($channel) => in_array($channel, $reportableChannels, true))
        ->values();
    $reportChannel = $hub->is_head_office
        ? (in_array($currentUser?->role, ['sales_associate', 'sales_marketing_staff'], true)
            ? $assignedReportChannels->first()
            : 'all')
        : 'walk_in';

    $hubActionGroups = [];
    $salesActions = [];
    $canRecordSale = $currentUser?->can('record-channel-sales')
        && (($hub->is_head_office && $currentUser?->role !== 'sales_associate')
            || (! $hub->is_head_office && in_array($currentUser?->role, ['admin', 'sales_associate'], true))
            || (! $hub->is_head_office && $currentUser?->role === 'inventory_staff' && $currentUser->hasSalesChannel('walk_in')));

    if ($canRecordSale) {
        $salesActions[] = [
            'label' => $hub->is_head_office ? 'Record Sale' : 'Record Walk-In',
            'icon' => $hub->is_head_office ? 'fa-cart-shopping' : 'fa-cash-register',
            'modal' => $hub->is_head_office ? '#recordSaleModal' : '#walkInSaleModal',
        ];
    }
    if ($currentUser?->can('view-sales-reports') && $reportChannel) {
        $salesActions[] = ['label' => 'Sales Report', 'icon' => 'fa-chart-line', 'url' => route('hub.report', ['hub' => $hub->id, 'channel' => $reportChannel])];
    }
    if ($currentUser?->can('verify-inventory')) {
        $salesActions[] = ['label' => 'Verify Sales', 'icon' => 'fa-clipboard-check', 'url' => route('hub.sales.pending', $hub->id), 'pending' => $pendingSalesCount ?? 0];
    }
    if (in_array($currentUser?->role, ['admin', 'inventory_staff', 'sales_associate', 'sales_marketing_staff'], true)) {
        $salesActions[] = ['label' => 'Rejected Sales', 'icon' => 'fa-circle-xmark', 'url' => route('sales.rejected', ['hub_id' => $hub->id])];
    }
    if (! $hub->is_head_office && $currentUser?->can('manage-branch-returns')) {
        $salesActions[] = ['label' => 'Return Items', 'icon' => 'fa-rotate-left', 'url' => route('hub.branch-returns', ['hub' => $hub->id])];
    }
    if ($salesActions !== []) {
        $hubActionGroups[] = ['title' => 'Sales operations', 'description' => 'Record, verify, and review store sales.', 'actions' => $salesActions];
    }

    if ($hub->is_head_office && in_array($currentUser?->role, ['admin', 'sales_marketing_staff'], true)) {
        $marketplaceActions = [];
        foreach (['shopee' => 'Shopee', 'lazada' => 'Lazada', 'tiktok' => 'TikTok'] as $channel => $label) {
            if ($currentUser->hasSalesChannel($channel)) {
                $marketplaceActions[] = ['label' => $label.' Orders', 'icon' => 'fa-bag-shopping', 'url' => route('hub.marketplace-orders', ['hub' => $hub->id, 'channel' => $channel])];
                $marketplaceActions[] = [
                    'label' => $label.' Returns',
                    'icon' => 'fa-rotate-left',
                    'url' => $channel === 'tiktok'
                        ? route('hub.tiktok-returns', ['hub' => $hub->id])
                        : route('hub.marketplace-returns', ['hub' => $hub->id, 'channel' => $channel]),
                ];
            }
        }
        if ($marketplaceActions !== []) {
            $hubActionGroups[] = ['title' => 'Marketplace operations', 'description' => 'Review orders and returns by marketplace.', 'actions' => $marketplaceActions];
        }
    }

    if ($hub->is_head_office && ($currentUser?->role === 'admin'
        || ($currentUser?->role === 'sales_marketing_staff' && $currentUser->hasSalesChannel('fully_booked')))) {
        $hubActionGroups[] = ['title' => 'Fully Booked operations', 'description' => 'Manage Head Office orders and return activity.', 'actions' => [
            ['label' => 'Fully Booked Order', 'icon' => 'fa-file-arrow-up', 'url' => route('inventory-transactions.sponsor.create', ['hub_id' => $hub->id, 'activity_type' => 'fully_booked'])],
            ['label' => 'Fully Booked Returns', 'icon' => 'fa-rotate-left', 'url' => route('hub.fully-booked-returns', ['hub' => $hub->id])],
            ['label' => 'Rejected Fully Booked', 'icon' => 'fa-circle-xmark', 'url' => route('hub.fully-booked-rejected', ['hub' => $hub->id])],
        ]];
    }

    $inventoryActions = [
        ['label' => 'Inventory', 'icon' => 'fa-boxes-stacked', 'url' => route('products.index', ['hub_id' => $hub->id])],
    ];
    if ($currentUser?->can('manage-inventory')) {
        $inventoryActions = array_merge($inventoryActions, [
            ['label' => 'Import CSV', 'icon' => 'fa-file-arrow-up', 'modal' => '#importProductModal'],
            ['label' => 'Export Products', 'icon' => 'fa-file-export', 'modal' => '#exportProductModal'],
        ]);
    }
    $hubActionGroups[] = ['title' => 'Inventory management', 'description' => 'Review inventory or exchange product files.', 'actions' => $inventoryActions];
@endphp
<style>
    .hub-action { display: flex !important; flex-direction: row !important; align-items: center !important; min-height: 64px; border: 1px solid #d7e3ff; border-radius: .5rem; background: #fff; color: var(--bs-primary); text-decoration: none; }
    .hub-action:hover { border-color: var(--bs-primary); background: var(--bs-primary); color: #fff; }
    .hub-action-icon { display: grid; flex: 0 0 2.1rem; width: 2.1rem; height: 2.1rem; margin-right: .65rem; place-items: center; border-radius: .65rem; background: #edf4ff; }
    .hub-action:hover .hub-action-icon { background: rgb(255 255 255 / 18%); }
    .hub-action-label { font-weight: 600; }
    .hub-action-groups { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .hub-action-group { border: 1px solid #dbe4f0; border-radius: 1rem; padding: 1rem; background: #fff; box-shadow: 0 .25rem .8rem rgb(15 23 42 / 5%); }
    .hub-action-heading { margin-bottom: .85rem; }
    .hub-action-heading h2 { margin: 0; color: #17345f; font-size: .78rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .hub-action-heading p { margin: .25rem 0 0; color: #64748b; font-size: .8rem; }
    .hub-action-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
    .hub-action-grid .hub-action { justify-content: flex-start !important; padding: .75rem 1rem; box-shadow: none; text-align: left; }
    @media (max-width: 991.98px) { .hub-action-groups { grid-template-columns: 1fr; } }
    @media (max-width: 575.98px) { .hub-action-grid { grid-template-columns: 1fr; } }
</style>
<div class="container-fluid p-4">
    <x-page-header class="mb-4" eyebrow="Sales management hub" :title="$hub->name" :description="$hub->is_head_office ? 'Head Office · Multi-channel operations' : 'Store · Walk-In operations'" :icon="$hub->is_head_office ? 'fa-building-columns' : 'fa-store'" />

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="hub-action-groups" data-hub-action-groups @if($currentUser?->role === 'admin') data-admin-hub-action-groups @endif>
        @foreach($hubActionGroups as $group)
        <section class="hub-action-group">
            <header class="hub-action-heading">
                <h2>{{ $group['title'] }}</h2>
                <p>{{ $group['description'] }}</p>
            </header>
            <div class="hub-action-grid">
                @foreach($group['actions'] as $action)
                    @if(isset($action['modal']))
                    <button type="button" class="hub-action w-100 position-relative" data-bs-toggle="modal" data-bs-target="{{ $action['modal'] }}">
                    @else
                    <a href="{{ $action['url'] }}" class="hub-action w-100 position-relative">
                    @endif
                        <span class="hub-action-icon"><i class="fa-solid {{ $action['icon'] }}"></i></span>
                        <span class="hub-action-label">{{ $action['label'] }}</span>
                        @if(($action['pending'] ?? 0) > 0)
                            <span class="badge rounded-pill bg-danger ms-auto" aria-label="{{ $action['pending'] }} inventory item(s) awaiting verification">{{ $action['pending'] }}</span>
                        @endif
                    @if(isset($action['modal']))
                    </button>
                    @else
                    </a>
                    @endif
                @endforeach
            </div>
        </section>
        @endforeach
    </div>
</div>

<script src="{{ asset('js/product-suggestions.js') }}?v=20260918-1439"></script>
@can('manage-inventory')
@include('hubs.export-modal')
@include('hubs.import-modal')
@endcan

@can('record-channel-sales')
@if($hub->is_head_office && auth()->user()?->role !== 'sales_associate')
    @include('hubs.record-sale-modal')
@elseif(auth()->user()?->role === 'sales_associate')
    @include('hubs.walk-in-sale-modal')
@elseif(auth()->user()?->role === 'inventory_staff' && auth()->user()->hasSalesChannel('walk_in'))
    @include('hubs.walk-in-sale-modal')
@elseif(!in_array(auth()->user()?->role, ['inventory_staff', 'sales_marketing_staff'], true))
    @include('hubs.walk-in-sale-modal')
@endif
@endcan
@endsection
