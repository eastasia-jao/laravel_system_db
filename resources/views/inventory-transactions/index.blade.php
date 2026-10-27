@extends('layouts.app')

@section('content')
<style>
    .inventory-log-table { font-size: .875rem; }
    .inventory-log-table th, .inventory-log-table td { padding: .55rem .65rem; white-space: nowrap; }
    .inventory-log-table td:nth-child(3), .inventory-log-table td:nth-child(8) { white-space: normal; }
    .fully-booked-preview { display: block; max-width: min(100%, 680px); max-height: 560px; margin-top: .75rem; border: 1px solid #e2e8f0; border-radius: .75rem; background: #f8fafc; }
    .fully-booked-pdf { width: min(100%, 760px); height: 560px; margin-top: .75rem; border: 1px solid #e2e8f0; border-radius: .75rem; }
    .return-detail-hero { background: linear-gradient(135deg, #eff6ff, #f8fafc); border-bottom: 1px solid #dbeafe; }
    .return-detail-kpi { border: 1px solid #e5e7eb; border-radius: .75rem; padding: .85rem 1rem; background: #fff; }
    .return-detail-kpi .value { font-size: 1.35rem; font-weight: 700; line-height: 1.1; }
    .return-detail-kpi.good .value { color: #198754; }
    .return-detail-kpi.damaged .value { color: #dc3545; }
    .return-detail-kpi.refund .value { color: #b02a37; }
    .return-detail-items { max-height: min(46vh, 420px); overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: #94a3b8 #f1f5f9; border: 1px solid #e5e7eb; border-radius: .75rem; }
    .return-detail-items::-webkit-scrollbar { width: 8px; }
    .return-detail-items::-webkit-scrollbar-track { background: #f1f5f9; }
    .return-detail-items::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .return-detail-item { padding: .85rem 1rem; background: #fff; }
    .return-detail-item + .return-detail-item { border-top: 1px solid #e5e7eb; }
    .return-detail-meta { color: #6c757d; font-size: .82rem; }
    .return-detail-condition { font-size: .72rem; }
    .replacement-detail-hero { border: 1px solid #bfdbfe; border-radius: 14px; padding: 1rem; background: linear-gradient(135deg, #eff6ff, #f8fafc); }
    .replacement-detail-hero .item-card { height: 100%; border: 1px solid #dbeafe; border-radius: 10px; padding: .85rem; background: #fff; }
    .replacement-detail-hero .item-card .label, .replacement-detail-section .label { color: #64748b; font-size: .72rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .replacement-detail-hero .item-card .name { font-weight: 700; margin-top: .25rem; }
    .replacement-detail-section { border: 1px solid #e5e7eb; border-radius: 12px; padding: .9rem; background: #fff; height: 100%; }
    .replacement-detail-section .value { margin-top: .2rem; font-weight: 600; }
    .replacement-detail-section .status { display: inline-flex; align-items: center; gap: .4rem; }
    .replacement-detail-notes { border-left: 4px solid #f59e0b; border-radius: 8px; padding: .75rem .9rem; background: #fffbeb; }
    .log-filter-card { border: 1px solid #e2e8f0; background: linear-gradient(135deg, #ffffff, #f8fafc); }
    .log-filter-card .form-label { letter-spacing: .05em; font-size: .7rem; }
    .log-table-card { border: 1px solid #e2e8f0; }
    .inventory-log-table thead th { background: #eff6ff; color: #1e3a8a; border-bottom: 2px solid #bfdbfe; font-size: .72rem; letter-spacing: .04em; }
    .inventory-log-table tbody tr { transition: background .15s ease, transform .15s ease; }
    .inventory-log-table tbody tr:hover { background: #f8fbff; }
    .log-type-badge { display: inline-flex; align-items: center; gap: .35rem; border-radius: 999px; padding: .3rem .6rem; font-size: .7rem; font-weight: 700; white-space: nowrap; }
    .log-type-badge.replacement { color: #1d4ed8; background: #dbeafe; }
    .log-type-badge.return { color: #92400e; background: #fef3c7; }
    .log-type-badge.sold { color: #166534; background: #dcfce7; }
    .log-type-badge.rejected { color: #b91c1c; background: #fee2e2; }
    .log-type-badge.transfer { width: 14.5rem; justify-content: center; color: #6b21a8; background: #f3e8ff; }
    .log-type-badge.other { color: #475569; background: #e2e8f0; }
    .channel-source-column { text-align: center; }
    .channel-source { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; min-width: 7rem; font-weight: 600; }
    .channel-source-icon { display: inline-grid; width: 1.65rem; height: 1.65rem; flex: 0 0 1.65rem; place-items: center; border-radius: .5rem; color: #475569; background: #f1f5f9; }
    .channel-source-icon.tiktok { color: #fff; background: #111827; }
    .channel-source-icon.shopee { color: #fff; background: #ee4d2d; }
    .channel-source-icon.lazada { color: #fff; background: linear-gradient(135deg, #3211a8, #f51b73); }
    .channel-source-icon.wholesale { color: #1d4ed8; background: #dbeafe; }
    .channel-source-icon.walk-in { color: #047857; background: #d1fae5; }
    .channel-source-icon.online { color: #0369a1; background: #e0f2fe; }
    .channel-source-icon.fully-booked { color: #92400e; background: #fef3c7; }
    .channel-source-icon.event { color: #7e22ce; background: #f3e8ff; }
    .channel-source-icon.national-bookstore { color: #b91c1c; background: #fee2e2; }
    .channel-source-icon.warehouse { color: #475569; background: #e2e8f0; }
    .log-detail-title { max-width: 360px; }
    .log-detail-title strong { display: block; overflow: hidden; text-overflow: ellipsis; }
    .log-view-button { border-radius: 999px; }
    .inventory-log-modal .modal-header { background: linear-gradient(135deg, #eff6ff, #ffffff); border-bottom: 1px solid #dbeafe; }
    .inventory-log-modal .modal-title { color: #1e3a8a; font-weight: 700; }
    .inventory-log-modal .modal-body { background: #f8fafc; }
    .inventory-log-modal .modal-footer { background: #fff; border-top: 1px solid #e2e8f0; }
    .inventory-log-modal .modal-body > .row { background: transparent; }
    .inventory-log-modal .modal-body > .row > [class*="col-"] > strong { display: block; color: #64748b; font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; }
    .inventory-log-modal .modal-body > .row > [class*="col-"] > strong + div { margin-top: .2rem; font-weight: 600; }
    .sold-order-image-preview { display: block; width: 100%; max-width: 680px; padding: .5rem; border: 1px solid #dbe3ee; border-radius: .75rem; background: #f8fafc; cursor: zoom-in; }
    .sold-order-image-preview img { display: block; width: 100%; max-height: 280px; object-fit: contain; }
    .inventory-order-image-viewer { position: relative; width: fit-content; max-width: calc(100vw - 2rem); max-height: calc(100vh - 2rem); padding: 1rem; border: 0; border-radius: 12px; background: #fff; box-shadow: 0 24px 70px rgba(0, 0, 0, .4); }
    .inventory-order-image-viewer::backdrop { background: rgba(15, 23, 42, .88); }
    .inventory-order-image-viewer img { display: block; max-width: calc(100vw - 4rem); max-height: calc(100vh - 7rem); object-fit: contain; }
    .inventory-order-image-viewer-caption { margin-top: .65rem; color: #1e3a8a; font-size: .85rem; font-weight: 700; text-align: center; }
    .inventory-order-image-viewer-close { position: absolute; top: .45rem; right: .45rem; z-index: 1; display: grid; width: 36px; height: 36px; place-items: center; border: 0; border-radius: 50%; color: #fff; background: rgba(15, 23, 42, .8); }
    .inventory-order-image-viewer-close:hover { background: #2563eb; }
    @supports (height: 100dvh) {
        .inventory-order-image-viewer { max-height: calc(100dvh - 2rem); }
        .inventory-order-image-viewer img { max-height: calc(100dvh - 7rem); }
    }
    .fully-booked-detail { display: grid; gap: 1rem; }
    .fully-booked-detail-summary { display: flex; align-items: center; gap: .9rem; padding: 1rem; border: 1px solid #dbeafe; border-radius: 14px; background: #fff; }
    .fully-booked-detail-icon { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; flex: 0 0 44px; border-radius: 12px; color: #2563eb; background: #eff6ff; }
    .fully-booked-detail-eyebrow { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .fully-booked-detail-order { margin: .15rem 0 0; color: #1e3a8a; font-size: 1.15rem; font-weight: 800; }
    .fully-booked-detail-status { margin-left: auto; }
    .fully-booked-detail-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); overflow: hidden; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
    .fully-booked-detail-meta-item { min-width: 0; padding: .85rem 1rem; }
    .fully-booked-detail-meta-item:nth-child(odd) { border-right: 1px solid #eef2f7; }
    .fully-booked-detail-meta-item:nth-child(n+3) { border-top: 1px solid #eef2f7; }
    .fully-booked-detail-label { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .fully-booked-detail-value { margin-top: .2rem; color: #1e293b; font-weight: 700; overflow-wrap: anywhere; }
    .fully-booked-detail-note { padding: .85rem 1rem; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; border-radius: 12px; background: #fff; }
    .fully-booked-detail-note.danger { border-color: #fecaca; border-left-color: #dc3545; background: #fff7f7; }
    .fully-booked-detail-note p { margin: .3rem 0 0; color: #334155; white-space: pre-wrap; overflow-wrap: anywhere; }
    .fully-booked-detail-section { overflow: hidden; border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; }
    .fully-booked-detail-section-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; border-bottom: 1px solid #eef2f7; background: #f8fafc; }
    .fully-booked-detail-section-title { color: #334155; font-size: .75rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; }
    .fully-booked-attachment-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1rem; }
    .fully-booked-attachment-file { display: flex; align-items: center; gap: .65rem; min-width: 0; color: #334155; }
    .fully-booked-attachment-file i { color: #64748b; }
    .fully-booked-attachment-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .fully-booked-detail-items { max-height: min(42vh, 360px); overflow-x: hidden; overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: #94a3b8 #f1f5f9; }
    .fully-booked-detail-items::-webkit-scrollbar { width: 8px; }
    .fully-booked-detail-items::-webkit-scrollbar-track { background: #f1f5f9; }
    .fully-booked-detail-items::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .fully-booked-detail-items::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .rejected-order-items-list { max-height: min(38vh, 320px); overflow-x: hidden; overflow-y: auto; overscroll-behavior-y: contain; scrollbar-gutter: stable; scrollbar-width: thin; scrollbar-color: #94a3b8 #f1f5f9; }
    .rejected-order-items-list::-webkit-scrollbar { width: 8px; }
    .rejected-order-items-list::-webkit-scrollbar-track { background: #f1f5f9; }
    .rejected-order-items-list::-webkit-scrollbar-thumb { border-radius: 999px; background: #94a3b8; }
    .rejected-order-items-list::-webkit-scrollbar-thumb:hover { background: #64748b; }
    .fully-booked-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .fully-booked-detail-item + .fully-booked-detail-item { border-top: 1px solid #eef2f7; }
    .fully-booked-detail-item-name { color: #1e293b; font-weight: 700; }
    .fully-booked-detail-item-id { margin-top: .15rem; color: #64748b; font-size: .78rem; }
    .fully-booked-detail-qty { flex: 0 0 auto; border-radius: 999px; padding: .3rem .65rem; color: #1d4ed8; background: #eff6ff; font-size: .78rem; font-weight: 800; }
    .fully-booked-detail-audit { padding: .7rem 1rem; border-top: 1px solid #eef2f7; color: #64748b; font-size: .78rem; }
    .fully-booked-detail-empty { display: flex; align-items: flex-start; gap: .8rem; padding: 1rem; border: 1px solid #dbeafe; border-radius: 14px; background: #eff6ff; }
    .fully-booked-detail-empty > i { margin-top: .15rem; color: #2563eb; }
    .sold-detail-hero { border: 1px solid #bbf7d0; border-radius: 16px; padding: 1.15rem; background: linear-gradient(135deg, #f0fdf4, #ffffff); }
    .sold-detail-hero .eyebrow { color: #15803d; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .sold-detail-hero h4 { color: #14532d; font-weight: 800; }
    .sold-detail-order { color: #166534; font-size: .82rem; }
    .sold-detail-kpi { height: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; background: #fff; }
    .sold-detail-kpi .label { color: #64748b; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .sold-detail-kpi .value { color: #0f172a; font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
    .sold-detail-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .sold-detail-section-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .sold-detail-section-title { color: #334155; font-size: .72rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
    .sold-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .sold-detail-item + .sold-detail-item { border-top: 1px solid #eef2f7; }
    .sold-detail-item-name { color: #1e293b; font-weight: 700; }
    .sold-detail-item-id { color: #64748b; font-size: .78rem; margin-top: .15rem; }
    .sold-detail-qty { min-width: 72px; color: #166534; font-weight: 800; text-align: right; }
    .sold-review-card { border-radius: 12px; padding: .9rem 1rem; background: #f8fafc; border: 1px solid #e2e8f0; }
    .sold-review-card.approved { border-color: #bbf7d0; background: #f0fdf4; }
    .sold-review-card.pending { border-color: #cbd5e1; background: #f8fafc; }
    .sold-replacement-item { display: flex; justify-content: space-between; gap: 1rem; padding: .8rem 1rem; }
    .sold-replacement-item + .sold-replacement-item { border-top: 1px solid #eef2f7; }
    .sold-activity-context { border-top: 1px dashed #cbd5e1; padding-top: 1rem; }
    .transfer-detail-hero { border: 1px solid #ddd6fe; border-radius: 16px; padding: 1.15rem; background: linear-gradient(135deg, #f5f3ff, #ffffff); }
    .transfer-detail-hero .eyebrow { color: #6d28d9; font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
    .transfer-detail-hero h4 { color: #4c1d95; font-weight: 800; }
    .transfer-detail-reference { color: #6d28d9; font-size: .82rem; }
    .transfer-detail-kpi { height: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: .85rem 1rem; background: #fff; }
    .transfer-detail-kpi .label { color: #64748b; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .transfer-detail-kpi .value { color: #0f172a; font-size: 1.35rem; font-weight: 800; line-height: 1.2; }
    .transfer-detail-route { height: 100%; border: 1px solid #c4b5fd; border-radius: 12px; padding: .85rem 1rem; background: #faf5ff; }
    .transfer-detail-route .label { color: #6d28d9; font-size: .7rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .transfer-detail-route .route { display: flex; align-items: center; gap: .65rem; margin-top: .45rem; color: #4c1d95; font-weight: 700; }
    .transfer-detail-route .route span { min-width: 0; }
    .transfer-detail-route .route i { color: #8b5cf6; flex: 0 0 auto; }
    .transfer-detail-section { border: 1px solid #e2e8f0; border-radius: 14px; background: #fff; overflow: hidden; }
    .transfer-detail-section-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
    .transfer-detail-section-title { color: #334155; font-size: .72rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; }
    .national-pullout-detail-items { max-height: min(46vh, 420px); overflow-y: auto; overscroll-behavior: contain; }
    .event-pullout-detail-items { max-height: min(46vh, 420px); overflow-y: auto; overscroll-behavior: contain; }
    .event-pullout-condition-line { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; margin-top: .25rem; color: #64748b; font-size: .78rem; }
    .event-pullout-condition-line .condition-label { font-weight: 700; }
    .event-pullout-condition-line.good .condition-label { color: #15803d; }
    .event-pullout-condition-line.damaged .condition-label { color: #b91c1c; }
    .transfer-detail-item { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 1rem; }
    .transfer-detail-item + .transfer-detail-item { border-top: 1px solid #eef2f7; }
    .transfer-detail-item-name { color: #1e293b; font-weight: 700; }
    .transfer-detail-item-id { color: #64748b; font-size: .78rem; margin-top: .15rem; }
    .transfer-detail-qty { min-width: 90px; color: #6d28d9; font-weight: 800; text-align: right; }
    .transfer-activity-context { border-top: 1px dashed #cbd5e1; padding-top: 1rem; }
    .transaction-logs-page .page-hero { padding: 1rem 1.25rem; }
    .transaction-logs-page .page-hero-icon { width: 44px; height: 44px; flex-basis: 44px; border-radius: 12px; }
    .inventory-action-panel { border: 1px solid #dbeafe; background: linear-gradient(135deg, #fff, #f8fbff); }
    .inventory-action-groups { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
    .inventory-action-group { min-width: 0; border: 1px solid #e2e8f0; border-radius: .85rem; padding: .8rem; background: #fff; }
    .inventory-action-group-title { color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; margin: 0 0 .55rem .15rem; }
    .inventory-action-links { display: grid; gap: .5rem; }
    .inventory-action-link { display: flex; align-items: center; gap: .7rem; min-height: 58px; padding: .65rem .75rem; border: 1px solid transparent; border-radius: .7rem; color: #0f172a; text-decoration: none; transition: border-color .15s ease, background .15s ease, transform .15s ease; }
    .inventory-action-link:hover { color: #1d4ed8; border-color: #bfdbfe; background: #eff6ff; transform: translateY(-1px); }
    .inventory-action-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; flex: 0 0 36px; border-radius: .65rem; color: #2563eb; background: #eff6ff; }
    .inventory-action-copy { min-width: 0; line-height: 1.2; }
    .inventory-action-copy strong { display: block; font-size: .88rem; }
    .inventory-action-copy small { display: block; color: #64748b; margin-top: .2rem; }
    @media (max-width: 767.98px) {
        .transaction-logs-page .page-hero { padding: 1rem; }
        .inventory-action-groups { grid-template-columns: 1fr; }
        .fully-booked-attachment-row { align-items: flex-start; flex-direction: column; }
        .fully-booked-detail-status { margin-left: 0; }
        .fully-booked-detail-summary { align-items: flex-start; flex-wrap: wrap; }
    }
    @media (max-width: 575.98px) {
        .fully-booked-detail-meta { grid-template-columns: 1fr; }
        .fully-booked-detail-meta-item:nth-child(odd) { border-right: 0; }
        .fully-booked-detail-meta-item + .fully-booked-detail-meta-item { border-top: 1px solid #eef2f7; }
        .fully-booked-detail-section-header { align-items: flex-start; flex-direction: column; }
    }
    @media (min-width: 768px) and (max-width: 1199.98px) { .inventory-action-groups { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>

<div class="workspace-page transaction-logs-page">
    <x-page-header class="mb-3" eyebrow="Inventory workspace" title="Inventory Transaction Logs" :description="auth()->user()?->role === 'sales_associate' ? 'Branch-to-branch stock transfers for '.($hubs->first()?->name ?? 'your designated branch').'.' : 'Monthly item movement for sold items, transfers, events, Fully Booked orders, restocks, and returns.'" icon="fa-clipboard-list" />

    @can('manage-inventory')
        @php
            $inventoryActionGroups = [
                ['label' => 'Stock movement', 'actions' => [
                    ['route' => 'inventory-transactions.transfer.create', 'icon' => 'fa-truck-ramp-box', 'title' => 'HO ↔ Branch Transfer', 'text' => 'Move stock between Head Office and a branch'],
                ]],
                ['label' => 'Event operations', 'actions' => [
                    ['route' => 'inventory-transactions.sponsor.create', 'icon' => 'fa-tents', 'title' => 'Event', 'text' => 'Record event inventory pull-outs', 'activity_type' => 'event', 'return_to' => 'transaction_logs'],
                    ['route' => 'inventory-transactions.sponsor.create', 'icon' => 'fa-book-open', 'title' => 'Fully Booked', 'text' => 'Review and process Fully Booked orders', 'activity_type' => 'fully_booked', 'pending' => $fullyBookedPulloutCount],
                ]],
                ['label' => 'Inventory updates', 'actions' => [
                    ['route' => 'inventory-transactions.restock.create', 'icon' => 'fa-boxes-stacked', 'title' => 'Restock from Warehouse', 'text' => 'Record stock added from a request'],
                    ['route' => 'inventory-transactions.return.create', 'icon' => 'fa-rotate-left', 'title' => 'Return Items', 'text' => 'Restore good or damaged stock', 'return_to' => 'transaction_logs'],
                ]],
            ];
            if ($canUseNationalPullout) {
                $inventoryActionGroups[1]['actions'][] = [
                    'route' => 'national-pullouts.create',
                    'icon' => 'fa-book-open',
                    'title' => 'National Bookstore Pullout',
                    'text' => 'Head Office only',
                    'hub_id' => $nationalHeadOfficeId,
                ];
            }
            if (auth()->user()->can('manage-inventory') || auth()->user()->can('submit-branch-transfers')) {
                $inventoryActionGroups[0]['actions'][] = [
                    'route' => 'inventory-transactions.branch-transfer.create',
                    'icon' => 'fa-right-left',
                    'title' => 'Stock Transfer (Branch to Branch)',
                    'text' => 'Move stock directly between branches',
                ];
            }
        @endphp
        <div class="card inventory-action-panel shadow-sm rounded-4 p-3 mb-3">
            <div class="inventory-action-groups">
                @foreach($inventoryActionGroups as $group)
                    <section class="inventory-action-group">
                        <h2 class="inventory-action-group-title">{{ $group['label'] }}</h2>
                        <div class="inventory-action-links">
                            @foreach($group['actions'] as $action)
                                <a href="{{ route($action['route'], ['hub_id' => $action['hub_id'] ?? $hubId, 'activity_type' => $action['activity_type'] ?? null, 'return_to' => $action['return_to'] ?? null]) }}" class="inventory-action-link position-relative">
                                    <span class="inventory-action-icon"><i class="fa-solid {{ $action['icon'] }}"></i></span>
                                    <span class="inventory-action-copy"><strong>{{ $action['title'] }}</strong><small>{{ $action['text'] }}</small></span>
                                    @if(($action['pending'] ?? 0) > 0)<span class="badge rounded-pill text-bg-danger ms-auto" aria-label="{{ $action['pending'] }} Fully Booked {{ $action['pending'] === 1 ? 'order' : 'orders' }} not yet processed">{{ $action['pending'] }}</span>@endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endcan

    @can('submit-branch-transfers')
        @cannot('manage-inventory')
            <div class="row row-cols-1 row-cols-md-2 g-3 mb-3 transaction-log-actions">
                <div class="col"><a href="{{ route('inventory-transactions.branch-transfer.create', ['hub_id' => $hubId]) }}" class="card border-0 shadow-sm rounded-4 p-3 text-decoration-none h-100">
                    <i class="fa-solid fa-right-left text-primary fs-4 mb-2"></i><strong class="text-dark">Stock Transfer</strong><small class="text-muted">BRANCH to BRANCH</small>
                </a></div>
            </div>
        @endcannot
    @endcan

    <div class="card log-filter-card shadow-sm rounded-4 p-3 mb-3">
        <form class="row g-2 align-items-end">
            <div class="col-md-3"><label class="small fw-bold text-muted">STORE HUB</label>
                <select name="hub_id" class="form-select"><option value="">All stores</option>
                    @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="small fw-bold text-muted">LOG TYPE</label>
                <select name="type" class="form-select">
                    @if(auth()->user()?->role !== 'sales_associate')<option value="">All types</option>@endif
                    @php
                        $logTypes = auth()->user()?->role === 'sales_associate'
                            ? []
                            : ['sold'=>'Sold Items','rejected_sale'=>'Rejected Orders','stock_transfer'=>'Stock Transfer (HO ↔ Branch)','branch_transfer'=>'Stock Transfer (BRANCH to BRANCH)','sponsor_workshop'=>'Event','fully_booked'=>'Fully Booked Orders','restock'=>'Restock / Added from Request (Warehouse HO)','return'=>'Return Items','replacement'=>'Replacement','national_bookstore_pullout'=>'National Bookstore Pullout'];
                    @endphp
                    @foreach($logTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="small fw-bold text-muted">STATUS</label>
                <select name="status" class="form-select">
                    <option value="">All statuses</option>
                    @foreach(['pending' => 'Pending review', 'approved' => 'Approved', 'rejected' => 'Rejected', 'reviewed' => 'Reviewed'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="small fw-bold text-muted">MONTH</label><input type="month" name="month" value="{{ request('month') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter logs</button></div>
        </form>
    </div>

    <div class="card log-table-card shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 inventory-log-table">
            <thead class="table-light small text-uppercase"><tr><th>Date / Time</th><th>Type</th><th>Transaction Details</th><th>Hub</th><th class="channel-source-column">Channel / Source</th><th class="text-end">Qty</th><th>Transfer</th><th>User</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($transactions as $groupKey => $group)
                @php
                    $transaction = $group->first();
                    $fullyBookedOrder = $transaction->relationLoaded('fullyBookedOrder')
                        ? $transaction->getRelation('fullyBookedOrder')
                        : null;
                    $isFullyBookedGroup = $transaction->type === 'fully_booked'
                        || ($transaction->type === 'sponsor_workshop' && $transaction->channel === 'fully_booked' && $fullyBookedOrder);
                    $fullyBookedOrder = $isFullyBookedGroup ? $fullyBookedOrder : null;
                    $isNationalReturn = $transaction->type === 'return' && $transaction->national_pullout_id;
                    $isEventReturn = $transaction->type === 'return' && $transaction->channel === 'event';
                    $isReturnGroup = $transaction->type === 'return' && ($transaction->salesTransaction || $isNationalReturn || $isEventReturn);
                    $fullyBookedReturnOrder = $isReturnGroup && $transaction->salesTransaction?->channel_type === 'fully_booked'
                        ? $transaction->getRelation('fullyBookedOrder')
                        : null;
                    $canPreviewFullyBookedReturn = $fullyBookedReturnOrder && (
                        in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)
                        || (auth()->user()?->role === 'sales_marketing_staff'
                            && auth()->user()->hasSalesChannel('fully_booked')
                            && ((int) $fullyBookedReturnOrder->submitted_by === (int) auth()->id()
                                || (int) $fullyBookedReturnOrder->sales_staff_id === (int) auth()->id()))
                    );
                    $isSoldOrder = $transaction->type === 'sold' && $transaction->salesTransaction;
                    $isRejectedSaleGroup = $transaction->type === 'rejected_sale';
                    $rejectedSale = $isRejectedSaleGroup ? $transaction->getRelation('rejectedSale') : null;
                    $isTransferGroup = in_array($transaction->type, ['stock_transfer', 'branch_transfer'], true);
                    $isSponsorGroup = $transaction->type === 'sponsor_workshop';
                    $isRestockGroup = $transaction->type === 'restock';
                    $isNationalPulloutGroup = $transaction->type === 'national_bookstore_pullout';
                    $nationalPullout = $isNationalPulloutGroup && $transaction->relationLoaded('nationalPullout')
                        ? $transaction->getRelation('nationalPullout')
                        : null;
                    $isReplacementGroup = $transaction->productReplacement !== null && ! $isReturnGroup;
                    $typeLabel = match (true) {
                        $isRejectedSaleGroup => 'Rejected Order',
                        $isFullyBookedGroup => 'Fully Booked',
                        $isReturnGroup => 'Return',
                        $isReplacementGroup => 'Replacement',
                        $isTransferGroup => $transaction->type === 'branch_transfer'
                            ? 'Stock Transfer (BRANCH to BRANCH)'
                            : ($transaction->sourceHub?->is_head_office ? 'Stock Transfer (HO to BRANCH)' : 'Stock Transfer (BRANCH to HO)'),
                        $isSponsorGroup => 'Event',
                        $isNationalPulloutGroup => 'National Bookstore Pullout',
                        default => ucwords(str_replace('_', ' ', $transaction->type)),
                    };
                    $modalId = 'transactionModal'.str_replace(['-', ' '], '', $groupKey);
                    $displayUser = $isRejectedSaleGroup
                        ? $rejectedSale?->rejectedBy
                        : ($isSoldOrder ? $transaction->salesTransaction?->user : $transaction->creator);
                    $sale = $transaction->salesTransaction;
                    $saleChannel = strtolower(str_replace(['-', ' '], '_', (string) $sale?->channel_type));
                    $orderSlipPath = $saleChannel === 'event'
                        ? ($sale?->event_attachments['order_slip'] ?? null)
                        : $sale?->order_slip;
                    $hasOrderSlip = $isSoldOrder && is_string($orderSlipPath) && $orderSlipPath !== '';
                    $orderSlipIsImage = $hasOrderSlip && in_array(strtolower(pathinfo($orderSlipPath, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
                    $orderSlipLabel = $saleChannel === 'walk_in' ? 'Walk-In Order Slip' : 'Order Attachment';
                    $orderSlipUrl = $hasOrderSlip ? route('inventory-transactions.sales-order-slip', $sale->id) : null;
                    $replacementReviews = $sale?->items
                        ? $sale->items->flatMap->replacements->filter(fn ($replacement) => $replacement->status !== 'pending')
                        : collect();
                    $channelSourceLabel = $transaction->channel
                        ? ucwords(str_replace('_', ' ', $transaction->channel))
                        : ($transaction->source ?: '—');
                    $channelSourceValue = strtolower(str_replace(['-', ' '], '_', (string) ($transaction->channel ?: $transaction->source)));
                    $channelSourceKey = match (true) {
                        str_contains($channelSourceValue, 'tiktok') => 'tiktok',
                        str_contains($channelSourceValue, 'shopee') => 'shopee',
                        str_contains($channelSourceValue, 'lazada') => 'lazada',
                        str_contains($channelSourceValue, 'wholesale') => 'wholesale',
                        str_contains($channelSourceValue, 'walk_in') => 'walk-in',
                        str_contains($channelSourceValue, 'online') => 'online',
                        str_contains($channelSourceValue, 'fully_booked') => 'fully-booked',
                        str_contains($channelSourceValue, 'national_bookstore') => 'national-bookstore',
                        str_contains($channelSourceValue, 'event') => 'event',
                        str_contains($channelSourceValue, 'warehouse') || str_contains($channelSourceValue, 'restock') => 'warehouse',
                        default => 'other',
                    };
                    if ($transaction->channel) {
                        $channelSourceLabel = match ($channelSourceKey) {
                            'tiktok' => 'TikTok',
                            'shopee' => 'Shopee',
                            'lazada' => 'Lazada',
                            'walk-in' => 'Walk-In',
                            'fully-booked' => 'Fully Booked',
                            'national-bookstore' => 'National Bookstore',
                            default => $channelSourceLabel,
                        };
                    }
                    [$channelIconFamily, $channelIcon] = match ($channelSourceKey) {
                        'tiktok' => ['fa-brands', 'fa-tiktok'],
                        'shopee' => ['fa-solid', 'fa-bag-shopping'],
                        'lazada' => ['fa-solid', 'fa-heart'],
                        'wholesale' => ['fa-solid', 'fa-boxes-stacked'],
                        'walk-in' => ['fa-solid', 'fa-store'],
                        'online' => ['fa-solid', 'fa-globe'],
                        'fully-booked', 'national-bookstore' => ['fa-solid', 'fa-book-open'],
                        'event' => ['fa-solid', 'fa-calendar-days'],
                        'warehouse' => ['fa-solid', 'fa-warehouse'],
                        default => ['fa-solid', 'fa-tag'],
                    };
                @endphp
                <tr>
                    <td>
                        <span class="d-block">{{ $transaction->occurred_on->format('M d, Y') }}</span>
                        <small class="text-muted">{{ $transaction->created_at?->format('h:i A') ?? 'Time unavailable' }}</small>
                    </td>
                    @php
                        $typeClass = $isRejectedSaleGroup ? 'rejected' : ($isReplacementGroup ? 'replacement' : ($isReturnGroup ? 'return' : ($isSoldOrder ? 'sold' : ($isTransferGroup ? 'transfer' : 'other'))));
                        $typeIcon = match (true) {
                            $isRejectedSaleGroup => 'fa-ban',
                            $isNationalPulloutGroup => 'fa-book-open',
                            $isFullyBookedGroup => 'fa-file-circle-check',
                            $isReplacementGroup => 'fa-arrow-right-arrow-left',
                            $isReturnGroup => 'fa-rotate-left',
                            $isSoldOrder => 'fa-cart-shopping',
                            $isTransferGroup => 'fa-truck-ramp-box',
                            default => 'fa-box',
                        };
                        $modalTitle = match (true) {
                            $isRejectedSaleGroup => 'Rejected Order Details',
                            $isNationalPulloutGroup => 'National Bookstore Pullout Details',
                            $isFullyBookedGroup => 'Fully Booked Order Details',
                            $isReplacementGroup => 'Replacement Details',
                            $isReturnGroup => 'Return Order Details',
                            $isSoldOrder => 'Sold Order Details',
                            default => $typeLabel.' Details',
                        };
                    @endphp
                    <td><span class="log-type-badge {{ $typeClass }}"><i class="fa-solid {{ $typeIcon }}"></i>{{ $typeLabel }}</span>
                        @if($transaction->type === 'branch_transfer' && $transaction->status !== 'approved')
                            <span class="badge mt-1 d-table {{ $transaction->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ strtoupper($transaction->status ?? 'pending') }}</span>
                        @endif
                    </td>
                    <td class="log-detail-title">
                        @if($isRejectedSaleGroup)
                            <strong>{{ $rejectedSale?->invoice_number ?: ($rejectedSale?->order_number ?? $transaction->reference) }}</strong>
                            <small class="d-block text-muted">{{ $rejectedSale?->customer_name ?: 'Customer not specified' }} · {{ $group->count() }} item line(s) rejected</small>
                        @elseif($isFullyBookedGroup)
                            <strong>{{ $fullyBookedOrder->order_number }}</strong>
                            <small class="d-block text-muted">{{ $fullyBookedOrder->salesStaff?->name ?? 'Deleted staff' }} · {{ $fullyBookedOrder->status === 'rejected' ? 'Order rejected' : ($fullyBookedOrder->pulled_out_at ? $group->count().' item line(s) · '.$group->sum('quantity').' total pulled out' : 'Attachment submitted for review') }}</small>
                        @elseif($isReplacementGroup)
                            <strong>{{ $transaction->productReplacement->originalProduct?->name ?? 'Original item' }} → {{ $transaction->productReplacement->replacementProduct?->name ?? 'Replacement item' }}</strong>
                            <small class="d-block text-muted">Returned {{ $transaction->productReplacement->quantity }} · Replaced with {{ $transaction->productReplacement->replacement_quantity ?: $transaction->productReplacement->quantity }}</small>
                        @elseif($isReturnGroup)
                            <strong>{{ $isNationalReturn ? 'Return P.O. #' : ($isEventReturn ? 'Event return' : 'Return order') }} {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</strong>
                            <small class="d-block text-muted">
                                {{ $isNationalReturn ? 'National Bookstore' : ($isEventReturn ? ($transaction->source ?: 'Event') : ($transaction->salesTransaction?->customer_name ?? 'Customer —')) }}
                                · {{ $group->where('condition', 'good')->sum('quantity') }} good
                                · {{ $group->where('condition', 'damaged')->sum('quantity') }} damaged
                                @if($group->sum('refund_amount') > 0) · Refund ₱{{ number_format($group->sum('refund_amount'), 2) }} @endif
                            </small>
                        @elseif($isSoldOrder)
                            <strong>Sold order {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} total sold</small>
                        @elseif($isTransferGroup)
                            <strong>{{ $transaction->reference ?: '—' }}</strong>
                        @elseif($isSponsorGroup)
                            <strong>{{ $transaction->reference ?: '—' }}</strong>
                        @elseif($isNationalPulloutGroup)
                            <strong>P.O. # {{ $nationalPullout?->po_number ?? $transaction->reference }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} actual pull-out</small>
                        @elseif($isRestockGroup)
                            <strong>{{ $transaction->reference ?: 'Restock' }}</strong>
                            <small class="d-block text-muted">{{ $group->count() }} item line(s) · {{ $group->sum('quantity') }} total added{{ $transaction->source ? ' · '.ucwords(str_replace('_', ' ', $transaction->source)) : '' }}</small>
                        @else
                            <strong>{{ $transaction->product?->name ?? 'Unknown product' }}</strong>
                            <small class="d-block text-muted">{{ $transaction->product?->item_id ?? '—' }}</small>
                        @endif
                    </td>
                    <td>{{ $transaction->storeHub?->name ?? '—' }}</td>
                    <td class="channel-source-column">
                        <span class="channel-source">
                            <span class="channel-source-icon {{ $channelSourceKey }}" aria-hidden="true"><i class="{{ $channelIconFamily }} {{ $channelIcon }}"></i></span>
                            <span>{{ $channelSourceLabel }}</span>
                        </span>
                    </td>
                    <td class="text-end fw-bold">{{ ($isFullyBookedGroup && ! $fullyBookedOrder->pulled_out_at) || $isRejectedSaleGroup ? '—' : $group->sum('quantity') }}</td>
                    <td>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '' }}</td>
                    <td>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-primary log-view-button" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}"><i class="fa-solid fa-eye me-1"></i>View details</button></td>
                </tr>
                <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content inventory-log-modal">
                        <div class="modal-header"><h5 class="modal-title">{{ $modalTitle }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            @if($isRejectedSaleGroup)
                                <div class="col-12">
                                    <div class="fully-booked-detail">
                                        <section class="fully-booked-detail-summary">
                                            <span class="fully-booked-detail-icon text-danger bg-danger-subtle"><i class="fa-solid fa-ban"></i></span>
                                            <div>
                                                <div class="fully-booked-detail-eyebrow">Rejected sales order · {{ ucwords(str_replace('_', ' ', $rejectedSale?->sales_channel ?? '')) }}</div>
                                                <h4 class="fully-booked-detail-order">{{ $rejectedSale?->invoice_number ?: ($rejectedSale?->order_number ?? $transaction->reference) }}</h4>
                                            </div>
                                        </section>
                                        <section class="fully-booked-detail-meta" aria-label="Rejected sales order information">
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Customer</div>
                                                <div class="fully-booked-detail-value">{{ $rejectedSale?->customer_name ?: 'Not specified' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Store Hub</div>
                                                <div class="fully-booked-detail-value">{{ $rejectedSale?->storeHub?->name ?? '—' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Submitted By</div>
                                                <div class="fully-booked-detail-value">{{ $rejectedSale?->submittedBy?->name ?? $rejectedSale?->submittedBy?->username ?? 'Deleted user' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Rejected By</div>
                                                <div class="fully-booked-detail-value">{{ $rejectedSale?->rejectedBy?->name ?? $rejectedSale?->rejectedBy?->username ?? 'Deleted user' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Rejected At</div>
                                                <div class="fully-booked-detail-value">{{ $rejectedSale?->rejected_at?->format('M d, Y h:i A') ?? '—' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Stock Impact</div>
                                                <div class="fully-booked-detail-value">No stock was deducted</div>
                                            </div>
                                        </section>
                                        <section class="fully-booked-detail-note danger">
                                            <div class="fully-booked-detail-label">Rejection Reason</div>
                                            <p>{{ $rejectedSale?->rejection_reason ?: 'No reason was recorded.' }}</p>
                                        </section>
                                        <section class="fully-booked-detail-section">
                                            <div class="fully-booked-detail-section-header">
                                                <span class="fully-booked-detail-section-title"><i class="fa-solid fa-box-open me-2 text-danger"></i>Rejected order items</span>
                                                <span class="small text-muted">{{ $group->count() }} line(s) · {{ $group->sum('quantity') }} requested</span>
                                            </div>
                                            <div class="rejected-order-items-list" aria-label="Rejected order items">
                                                @foreach($group as $entry)
                                                    <div class="transfer-detail-item align-items-start">
                                                        <div>
                                                            <div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div>
                                                            <div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div>
                                                        </div>
                                                        <div class="transfer-detail-qty">Requested {{ $entry->quantity }}</div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </section>
                                    </div>
                                </div>
                            @elseif($isNationalPulloutGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow"><i class="fa-solid fa-book-open me-1"></i>National Bookstore pull-out</div>
                                                <h4 class="mb-1 mt-2">P.O. # {{ $nationalPullout?->po_number ?? $transaction->reference }}</h4>
                                                <div class="transfer-detail-reference">{{ $nationalPullout?->occurred_on?->format('M d, Y') ?? $transaction->occurred_on?->format('M d, Y') }}</div>
                                            </div>
                                            @if($nationalPullout)
                                                <a href="{{ route('national-pullouts.export', ['nationalPullout' => $nationalPullout, 'hub_id' => $nationalPullout->store_hub_id]) }}" class="btn btn-sm btn-outline-success"><i class="fa-solid fa-file-csv me-1"></i>Download CSV</a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Head Office</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                @if($nationalPullout?->remarks)
                                    <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Remarks</div><div style="white-space:pre-wrap">{{ $nationalPullout->remarks }}</div></div></div>
                                @endif
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Items pulled out</span><span class="small text-muted">{{ $group->count() }} line(s) · {{ $group->sum('quantity') }} actual pull-out</span></div>
                                        <div class="national-pullout-detail-items" aria-label="National Bookstore pull-out items">
                                            @foreach($group as $entry)
                                                @php
                                                    $pulloutItem = $entry->getRelation('nationalPulloutItem');
                                                @endphp
                                                <div class="transfer-detail-item align-items-start">
                                                    <div>
                                                        <div class="transfer-detail-item-name">{{ $pulloutItem?->product_name ?? 'Unknown product' }}</div>
                                                        <div class="transfer-detail-item-id">Item ID: {{ $pulloutItem?->item_id ?? '—' }} · Requested qty: {{ $pulloutItem?->quantity ?? 0 }}</div>
                                                    </div>
                                                    <div class="transfer-detail-qty">Actual {{ $pulloutItem?->actual_pullout ?? $entry->quantity }}</div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @elseif($isFullyBookedGroup)
                                <div class="col-12">
                                    @php
                                        $fullyBookedStatusLabel = $fullyBookedOrder?->status === 'rejected'
                                            ? 'Rejected'
                                            : ($fullyBookedOrder?->pulled_out_at ? 'Completed' : ($fullyBookedOrder?->status === 'reviewed' ? 'Reviewed' : 'Pending'));
                                        $fullyBookedStatusClass = $fullyBookedOrder?->status === 'rejected'
                                            ? 'text-bg-danger'
                                            : ($fullyBookedOrder?->pulled_out_at ? 'text-bg-success' : 'text-bg-warning');
                                    @endphp
                                    <div class="fully-booked-detail">
                                        <section class="fully-booked-detail-summary">
                                            <span class="fully-booked-detail-icon"><i class="fa-solid fa-book-open"></i></span>
                                            <div>
                                                <div class="fully-booked-detail-eyebrow">Fully Booked order</div>
                                                <h4 class="fully-booked-detail-order">{{ $fullyBookedOrder?->order_number ?? $transaction->reference }}</h4>
                                            </div>
                                            <span class="badge rounded-pill {{ $fullyBookedStatusClass }} fully-booked-detail-status">{{ $fullyBookedStatusLabel }}</span>
                                        </section>

                                        <section class="fully-booked-detail-meta" aria-label="Fully Booked order information">
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Sales / Marketing Staff</div>
                                                <div class="fully-booked-detail-value">{{ $fullyBookedOrder?->salesStaff?->name ?? 'Deleted staff' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Submitted By</div>
                                                <div class="fully-booked-detail-value">{{ $fullyBookedOrder?->submitter?->name ?? 'Deleted user' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Store Hub</div>
                                                <div class="fully-booked-detail-value">{{ $fullyBookedOrder?->storeHub?->name ?? '—' }}</div>
                                            </div>
                                            <div class="fully-booked-detail-meta-item">
                                                <div class="fully-booked-detail-label">Submitted</div>
                                                <div class="fully-booked-detail-value">{{ $fullyBookedOrder?->created_at?->format('M d, Y h:i A') ?? '—' }}</div>
                                            </div>
                                        </section>

                                        @if($fullyBookedOrder?->status === 'rejected')
                                            <section class="fully-booked-detail-note danger">
                                                <div class="fully-booked-detail-label text-danger">Rejected by {{ $fullyBookedOrder->reviewer?->name ?? 'Inventory staff' }} · {{ $fullyBookedOrder->reviewed_at?->format('M d, Y h:i A') ?? 'Time unavailable' }}</div>
                                                <p>{{ $fullyBookedOrder->rejection_reason ?: 'No rejection reason provided.' }}</p>
                                            </section>
                                        @endif

                                        @if($fullyBookedOrder?->remarks)
                                            <section class="fully-booked-detail-note">
                                                <div class="fully-booked-detail-label">Remarks</div>
                                                <p>{{ $fullyBookedOrder->remarks }}</p>
                                            </section>
                                        @endif

                                        <section class="fully-booked-detail-section">
                                            <div class="fully-booked-detail-section-header">
                                                <span class="fully-booked-detail-section-title"><i class="fa-solid fa-paperclip me-2 text-primary"></i>Order attachment</span>
                                            </div>
                                            <div class="fully-booked-attachment-row">
                                                <div class="fully-booked-attachment-file">
                                                    <i class="fa-solid {{ str_starts_with($fullyBookedOrder?->mime_type ?? '', 'image/') ? 'fa-file-image' : 'fa-file-pdf' }}"></i>
                                                    <span title="{{ $fullyBookedOrder?->original_filename }}">{{ $fullyBookedOrder?->original_filename ?? 'Attachment' }}</span>
                                                </div>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedOrder) }}" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i>Preview</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('inventory-transactions.fully-booked.attachment', ['fullyBookedOrder' => $fullyBookedOrder, 'download' => 1]) }}"><i class="fa-solid fa-download me-1"></i>Download</a>
                                                </div>
                                            </div>
                                        </section>

                                        @if($fullyBookedOrder?->pulled_out_at)
                                            <section class="fully-booked-detail-section">
                                                <div class="fully-booked-detail-section-header">
                                                    <div>
                                                        <div class="fully-booked-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2 text-primary"></i>Items Pulled Out</div>
                                                        <div class="small text-muted mt-1">{{ $fullyBookedOrder->items->count() }} item line(s) · {{ $fullyBookedOrder->items->sum('quantity') }} total quantity</div>
                                                    </div>
                                                    <button type="button" class="btn btn-sm btn-outline-success export-fully-booked-xlsx"
                                                        data-order-number="{{ $fullyBookedOrder->order_number }}"
                                                        data-completed="true"
                                                        data-export-url="{{ route('inventory-transactions.fully-booked.export-worksheet', $fullyBookedOrder) }}">
                                                        <i class="fa-solid fa-file-excel me-1"></i>Export Excel
                                                    </button>
                                                </div>
                                                <div class="fully-booked-detail-items" aria-label="Items pulled out">
                                                    @foreach($fullyBookedOrder->items->sortBy('item_id', SORT_NATURAL | SORT_FLAG_CASE) as $item)
                                                        <div class="fully-booked-detail-item fully-booked-export-item"
                                                            data-item-id="{{ $item->item_id }}"
                                                            data-description="{{ $item->product?->description ?: $item->product_name }}"
                                                            data-barcode="{{ $item->product?->barcode ?? '' }}"
                                                            data-stock="{{ $item->product?->stock ?? '' }}"
                                                            data-quantity="{{ $item->quantity }}">
                                                            <div>
                                                                <div class="fully-booked-detail-item-name">{{ $item->product_name }}</div>
                                                                <div class="fully-booked-detail-item-id">Item ID: {{ $item->item_id ?? '—' }}</div>
                                                            </div>
                                                            <span class="fully-booked-detail-qty">Qty {{ $item->quantity }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                                <div class="fully-booked-detail-audit"><i class="fa-solid fa-clock-rotate-left me-1"></i>Recorded by {{ $fullyBookedOrder->pullOutBy?->name ?? 'Inventory staff' }} · {{ $fullyBookedOrder->pulled_out_at->format('M d, Y h:i A') }}</div>
                                            </section>
                                        @elseif($fullyBookedOrder?->status !== 'rejected')
                                            <section class="fully-booked-detail-empty">
                                                <i class="fa-solid fa-clock"></i>
                                                <div class="flex-grow-1">
                                                    <div class="fw-bold text-primary">Awaiting inventory pull-out</div>
                                                    <p class="small text-muted mb-2">Review the attachment, then record the products and quantities from the Fully Booked page.</p>
                                                    <a class="btn btn-sm btn-primary" href="{{ route('inventory-transactions.sponsor.create', ['hub_id' => $fullyBookedOrder->store_hub_id, 'activity_type' => 'fully_booked']) }}">Open Fully Booked</a>
                                                </div>
                                            </section>
                                        @endif
                                    </div>
                                </div>
                            @elseif($isReplacementGroup)
                                @php
                                    $replacement = $transaction->productReplacement;
                                    $channelLabel = ucwords(str_replace(['_', '-'], ' ', (string) ($replacement->transaction?->channel_type ?? $transaction->channel ?? 'sales')));
                                @endphp
                                <div class="col-12"><div class="replacement-detail-hero"><div class="text-uppercase small fw-bold text-primary mb-2"><i class="fa-solid fa-arrow-right-arrow-left me-1"></i>Product exchange <span class="ms-2 text-muted">· {{ $channelLabel }} channel pricing</span></div><div class="row g-3"><div class="col-md-6"><div class="item-card"><div class="label">Original item returned</div><div class="name">{{ $replacement->originalProduct?->name ?? '—' }}</div><div class="small text-muted mt-1">Quantity returned: <strong>{{ $replacement->quantity }}</strong></div><div class="small text-primary mt-2">Original {{ $channelLabel }} price: <strong>₱{{ number_format((float) $replacement->original_unit_price, 2) }}</strong> / unit</div></div></div><div class="col-md-6"><div class="item-card"><div class="label">Replacement item released</div><div class="name">{{ $replacement->replacementProduct?->name ?? '—' }}</div><div class="small text-muted mt-1">Quantity released: <strong>{{ $replacement->replacement_quantity ?: $replacement->quantity }}</strong></div><div class="small text-primary mt-2">Replacement {{ $channelLabel }} price: <strong>₱{{ number_format((float) $replacement->replacement_unit_price, 2) }}</strong> / unit</div></div></div></div></div></div>
                                <div class="col-md-6"><div class="replacement-detail-section"><div class="label">Customer name</div><div class="value">{{ $replacement->transaction?->customer_name ?: '—' }}</div></div></div>
                                <div class="col-md-6"><div class="replacement-detail-section"><div class="label">Order number</div><div class="value">{{ $replacement->transaction?->order_number ?: ($transaction->reference ?: '—') }}</div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Status</div><div class="value status"><span class="badge {{ $replacement->status === 'approved' ? 'bg-success' : ($replacement->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ strtoupper($replacement->status) }}</span></div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Reviewed by</div><div class="value">{{ $replacement->reviewer?->name ?? $replacement->reviewer?->username ?? 'Not reviewed' }}</div></div></div>
                                <div class="col-md-4"><div class="replacement-detail-section"><div class="label">Reviewed at</div><div class="value">{{ optional($replacement->reviewed_at)->format('M d, Y h:i A') ?? 'Not reviewed' }}</div></div></div>
                                @if($replacement->rejection_reason)<div class="col-12"><div class="replacement-detail-notes"><strong>Rejection reason</strong><div class="text-danger mt-1">{{ $replacement->rejection_reason }}</div></div></div>@endif
                            @elseif($isReturnGroup)
                                <div class="col-12">
                                    <div class="return-detail-hero rounded-3 p-3 mb-3">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                            <div>
                                                <div class="text-uppercase small fw-bold text-primary">Return record</div>
                                                <h5 class="mb-1">{{ $isNationalReturn ? 'National Bookstore' : ($isEventReturn ? ($transaction->source ?: 'Event') : ($transaction->salesTransaction?->customer_name ?? 'Customer —')) }}</h5>
                                                <div class="return-detail-meta">{{ $isNationalReturn ? 'P.O. #' : ($isEventReturn ? 'Event reference' : 'Order') }} {{ $transaction->salesTransaction?->order_number ?? $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge text-bg-info">RETURN ITEMS</span>
                                        </div>
                                    </div>
                                    @php
                                        $returnChannel = strtolower(str_replace(['-', ' '], '_', (string) ($transaction->salesTransaction?->channel_type ?? $transaction->channel ?? '')));
                                        $showReturnRefund = ! $isFullyBookedGroup
                                            && ! in_array($returnChannel, ['shopee', 'lazada', 'tiktok', 'fully_booked', 'national_bookstore', 'event'], true);
                                    @endphp
                                    <div class="row g-2 mb-3">
                                        <div class="{{ $showReturnRefund ? 'col-md-4' : 'col-md-6' }}"><div class="return-detail-kpi good"><div class="small text-muted">Good quantity</div><div class="value">{{ $group->where('condition', 'good')->sum('quantity') }}</div></div></div>
                                        <div class="{{ $showReturnRefund ? 'col-md-4' : 'col-md-6' }}"><div class="return-detail-kpi damaged"><div class="small text-muted">Damaged quantity</div><div class="value">{{ $group->where('condition', 'damaged')->sum('quantity') }}</div></div></div>
                                        @if($showReturnRefund)
                                            <div class="col-md-4"><div class="return-detail-kpi refund"><div class="small text-muted">Refund total</div><div class="value">₱{{ number_format($group->sum('refund_amount'), 2) }}</div></div></div>
                                        @endif
                                    </div>
                                </div>
                                @if($canPreviewFullyBookedReturn)
                                    <div class="col-12">
                                        <div class="transfer-detail-section">
                                            <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-paperclip me-2 text-primary"></i>Fully Booked attachment</span></div>
                                            <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" target="_blank" rel="noopener">Open attachment</a>
                                            @if(str_starts_with($fullyBookedReturnOrder->mime_type ?? '', 'image/'))
                                                <img src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" alt="Attachment for Fully Booked order {{ $fullyBookedReturnOrder->order_number }}" class="fully-booked-preview">
                                            @else
                                                <iframe src="{{ route('inventory-transactions.fully-booked.attachment', $fullyBookedReturnOrder) }}" title="Attachment for Fully Booked order {{ $fullyBookedReturnOrder->order_number }}" class="fully-booked-pdf"></iframe>
                                            @endif
                                            <div class="small text-muted mt-2">{{ $fullyBookedReturnOrder->original_filename }}</div>
                                        </div>
                                    </div>
                                @endif
                                @php
                                    $returnProductGroups = $group->groupBy(function ($entry) {
                                        if ($entry->product_id) {
                                            return 'product-'.$entry->product_id;
                                        }
                                        if ($entry->national_product_id) {
                                            return 'national-'.$entry->national_product_id;
                                        }

                                        return 'transaction-'.$entry->id;
                                    })->map(function ($entries) {
                                        $conditions = $entries->groupBy(fn ($entry) => strtolower((string) ($entry->condition ?: 'other')))
                                            ->map(fn ($conditionEntries) => (int) $conditionEntries->sum('quantity'));

                                        return [
                                            'entry' => $entries->first(),
                                            'conditions' => $conditions,
                                            'quantity' => (int) $entries->sum('quantity'),
                                            'refund_amount' => (float) $entries->sum('refund_amount'),
                                        ];
                                    });
                                @endphp
                                <div class="col-12"><strong class="d-block mb-2">Returned items</strong><div class="return-detail-items">
                                    @foreach($returnProductGroups as $returnItem)
                                        @php($entry = $returnItem['entry'])
                                        <div class="return-detail-item d-flex justify-content-between align-items-center gap-3">
                                            <div>
                                                <strong>{{ $entry->product?->name ?? $entry->nationalProduct?->name ?? 'Unknown product' }}</strong>
                                                <div class="return-detail-meta">Item ID: {{ $entry->product?->item_id ?? $entry->nationalProduct?->item_id ?? '—' }}</div>
                                                <div class="d-flex flex-wrap gap-1 mt-2">
                                                    @foreach($returnItem['conditions'] as $condition => $quantity)
                                                        @if($condition !== 'other')
                                                            <span class="badge {{ $condition === 'good' ? 'text-bg-success' : ($condition === 'damaged' ? 'text-bg-danger' : 'text-bg-secondary') }} return-detail-condition">{{ ucfirst($condition) }}: {{ $quantity }}</span>
                                                        @else
                                                            <span class="badge text-bg-secondary return-detail-condition">Other: {{ $quantity }}</span>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="small text-muted">Total Qty</div>
                                                <strong>{{ $returnItem['quantity'] }}</strong>
                                                @if($returnItem['refund_amount'] > 0)
                                                    <div class="small mt-1">Refund <strong>₱{{ number_format($returnItem['refund_amount'], 2) }}</strong></div>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div></div>
                            @elseif($isSoldOrder)
                                <div class="col-12">
                                    <div class="sold-detail-hero">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow"><i class="fa-solid fa-cart-shopping me-1"></i>Completed sales record</div>
                                                <h4 class="mb-1 mt-2">Sold order</h4>
                                                <div class="sold-detail-order">Order {{ $sale->order_number ?? $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge text-bg-success rounded-pill px-3 py-2"><i class="fa-solid fa-check me-1"></i>SOLD</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Item lines</div><div class="value">{{ $group->count() }}</div></div></div>
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Total sold</div><div class="value">{{ $group->sum('quantity') }}</div></div></div>
                                        <div class="col-md-4"><div class="sold-detail-kpi"><div class="label">Sales channel</div><div class="value fs-5">{{ ucwords(str_replace('_', ' ', $transaction->channel ?: $transaction->source ?: '—')) }}</div></div></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="sold-detail-section">
                                        <div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-box-open me-2 text-success"></i>Sold items</span><span class="small text-muted">{{ $group->count() }} line(s)</span></div>
                                        @foreach($group as $entry)
                                            @php($soldItem = $sale->items->first(fn ($item) => (int) $item->product_id === (int) $entry->product_id))
                                            <div class="sold-detail-item">
                                                <div><div class="sold-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="sold-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div><div class="small text-primary mt-1">Channel price: <strong>₱{{ number_format((float) ($soldItem?->unit_price ?? 0), 2) }}</strong> / unit{{ $soldItem?->line_total !== null ? ' · Line total ₱'.number_format((float) $soldItem->line_total, 2) : '' }}</div></div>
                                                <div class="sold-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="sold-detail-section">
                                        <div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Order review</span></div>
                                        <div class="p-3">
                                            @if($sale->confirmedBy)
                                                <div class="sold-review-card approved d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                    <div><span class="badge text-bg-success rounded-pill">APPROVED</span><span class="ms-2 fw-semibold">{{ $sale->confirmedBy->name ?? $sale->confirmedBy->username }}</span></div>
                                                    @if($sale->confirmed_at)<span class="small text-muted">{{ $sale->confirmed_at->format('M d, Y h:i A') }}</span>@endif
                                                </div>
                                            @else
                                                <div class="sold-review-card pending"><span class="badge text-bg-secondary rounded-pill">NOT REVIEWED</span><span class="small text-muted ms-2">This order has not been reviewed yet.</span></div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @if($replacementReviews->isNotEmpty())
                                    <div class="col-12"><div class="sold-detail-section"><div class="sold-detail-section-header"><span class="sold-detail-section-title"><i class="fa-solid fa-arrow-right-arrow-left me-2 text-primary"></i>Replacement reviews</span><span class="small text-muted">{{ $replacementReviews->count() }} review(s)</span></div>
                                        @foreach($replacementReviews as $replacement)
                                            <div class="sold-replacement-item"><div><div class="fw-semibold">{{ $replacement->originalProduct?->name ?? 'Original item' }} <span class="text-muted">→</span> {{ $replacement->replacementProduct?->name ?? 'Replacement item' }}</div><div class="small text-muted mt-1">Reviewer: {{ $replacement->reviewer?->name ?? $replacement->reviewer?->username ?? 'Unknown reviewer' }}</div></div><span class="badge {{ $replacement->status === 'approved' ? 'text-bg-success' : 'text-bg-danger' }} align-self-start">{{ strtoupper($replacement->status) }}</span></div>
                                        @endforeach
                                    </div></div>
                                @endif
                            @elseif($isSponsorGroup)
                                @php($eventProducts = $group->groupBy('product_id'))
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="eyebrow"><i class="fa-solid fa-people-group me-1"></i>Event record</div>
                                        <h4 class="mb-1 mt-2">{{ $transaction->source ?: 'Event or recipient not specified' }}</h4>
                                        <div class="transfer-detail-reference">{{ $transaction->reference ?? '—' }}</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:#6d28d9;"></i>Items pulled out</span><span class="small text-muted">{{ $eventProducts->count() }} product(s) · {{ $group->sum('quantity') }} actual released{{ $group->sum('pullout_qty') ? ' · '.$group->sum('pullout_qty').' requested' : '' }}</span></div>
                                        <div class="event-pullout-detail-items" aria-label="Event pull-out items">
                                            @foreach($eventProducts as $productEntries)
                                                @php($productEntry = $productEntries->first())
                                                <div class="transfer-detail-item align-items-start">
                                                    <div>
                                                        <div class="transfer-detail-item-name">{{ $productEntry->product?->name ?? 'Unknown product' }}</div>
                                                        <div class="transfer-detail-item-id">Item ID: {{ $productEntry->product?->item_id ?? '—' }}</div>
                                                        @foreach($productEntries->groupBy(fn ($entry) => $entry->condition ?: 'good') as $condition => $conditionEntries)
                                                            <div class="event-pullout-condition-line {{ $condition === 'damaged' ? 'damaged' : 'good' }}">
                                                                <span class="condition-label">{{ ucfirst($condition) }}</span>
                                                                @if($conditionEntries->sum('pullout_qty') > 0)
                                                                    <span>· Requested {{ $conditionEntries->sum('pullout_qty') }}</span>
                                                                @endif
                                                                <span>· Actual {{ $conditionEntries->sum('quantity') }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @elseif($isTransferGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                            <div>
                                                <div class="eyebrow"><i class="fa-solid fa-truck-ramp-box me-1"></i>Inventory movement record</div>
                                                <h4 class="mb-1 mt-2">{{ $transaction->type === 'branch_transfer' ? 'Branch-to-branch transfer' : 'Stock transfer' }}</h4>
                                                <div class="transfer-detail-reference">{{ $transaction->reference ?? '—' }}</div>
                                            </div>
                                            <span class="badge rounded-pill px-3 py-2 {{ $transaction->status === 'approved' ? 'text-bg-success' : ($transaction->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}"><i class="fa-solid {{ $transaction->status === 'approved' ? 'fa-check' : ($transaction->status === 'rejected' ? 'fa-xmark' : 'fa-hourglass-half') }} me-1"></i>{{ strtoupper($transaction->status ?? 'approved') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="row g-2">
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Item lines</div><div class="value">{{ $group->count() }}</div></div></div>
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Total quantity</div><div class="value">{{ $group->sum('quantity') }}</div></div></div>
                                        <div class="col-md-4"><div class="transfer-detail-kpi"><div class="label">Recorded by</div><div class="value fs-5">{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</div></div></div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-route">
                                        <div class="label"><i class="fa-solid fa-route me-1"></i>Transfer route</div>
                                        <div class="route">
                                            <span class="text-truncate">{{ $transaction->sourceHub?->name ?? 'Source hub —' }}</span>
                                            <i class="fa-solid fa-arrow-right"></i>
                                            <span class="text-truncate">{{ $transaction->targetHub?->name ?? 'Destination hub —' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-boxes-stacked me-2" style="color:#6d28d9;"></i>{{ $transaction->status === 'pending' ? 'Requested items' : 'Transferred items' }}</span><span class="small text-muted">{{ $group->count() }} line(s)</span></div>
                                        @foreach($group as $entry)
                                            <div class="transfer-detail-item">
                                                <div><div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                                <div class="transfer-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @if($transaction->type === 'branch_transfer')
                                    <div class="col-md-6"><div class="transfer-detail-section p-3"><strong class="d-block">Reviewed by</strong><div>{{ $transaction->reviewer?->name ?? $transaction->reviewer?->username ?? 'Pending review' }}</div></div></div>
                                    <div class="col-md-6"><div class="transfer-detail-section p-3"><strong class="d-block">Reviewed at</strong><div>{{ $transaction->reviewed_at?->format('M d, Y h:i A') ?? 'Pending review' }}</div></div></div>
                                    @if($transaction->rejection_reason)
                                        <div class="col-12"><div class="alert alert-danger mb-0"><strong>Rejection reason</strong><div>{{ $transaction->rejection_reason }}</div></div></div>
                                    @endif
                                    @if($transaction->status === 'pending' && auth()->user()?->can('verify-inventory') && (int) $transaction->created_by !== (int) auth()->id())
                                        <div class="col-12 border-top pt-3 mt-2">
                                            <div class="fw-bold mb-2">Review transfer request</div>
                                            <div class="d-flex flex-wrap align-items-end gap-2">
                                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transaction->transfer_batch_id]) }}">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="approved">
                                                    <button class="btn btn-success"><i class="fa-solid fa-check me-1"></i>Approve and transfer stock</button>
                                                </form>
                                                <form method="POST" action="{{ route('inventory-transactions.branch-transfer.review', ['batch' => $transaction->transfer_batch_id]) }}" class="d-flex flex-wrap align-items-end gap-2">
                                                    @csrf
                                                    <input type="hidden" name="decision" value="rejected">
                                                    <div><label class="form-label small mb-1">Rejection reason</label><input name="rejection_reason" class="form-control" maxlength="2000" required></div>
                                                    <button class="btn btn-outline-danger"><i class="fa-solid fa-xmark me-1"></i>Reject request</button>
                                                </form>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            @elseif($isRestockGroup)
                                <div class="col-12">
                                    <div class="transfer-detail-hero">
                                        <div class="eyebrow"><i class="fa-solid fa-boxes-stacked me-1"></i>Restock / Added from Request (Warehouse HO)</div>
                                        <h4 class="mb-1 mt-2">{{ $transaction->reference ?: 'Restock record' }}</h4>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="transfer-detail-section">
                                        <div class="transfer-detail-section-header"><span class="transfer-detail-section-title"><i class="fa-solid fa-box-open me-2 text-primary"></i>Items added</span><span class="small text-muted">{{ $group->count() }} line(s) · {{ $group->sum('quantity') }} total</span></div>
                                        @foreach($group as $entry)
                                            <div class="transfer-detail-item">
                                                <div><div class="transfer-detail-item-name">{{ $entry->product?->name ?? 'Unknown product' }}</div><div class="transfer-detail-item-id">Item ID: {{ $entry->product?->item_id ?? '—' }}</div></div>
                                                <div class="transfer-detail-qty">Qty {{ $entry->quantity }}</div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="col-md-6"><strong>Product</strong><div>{{ $transaction->product?->name ?? 'Unknown product' }} ({{ $transaction->product?->item_id ?? '—' }})</div></div>
                                <div class="col-md-3"><strong>Quantity</strong><div>{{ $transaction->quantity }}</div></div>
                            @endif
                            @if(!$isReplacementGroup && !$isFullyBookedGroup && !$isNationalPulloutGroup && !$isRejectedSaleGroup)
                                @if($isSponsorGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Event / Recipient</div><strong>{{ $transaction->source ?: '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isSoldOrder)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Submitted by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Sales Channel</div><strong>{{ ucwords(str_replace('_', ' ', $transaction->channel ?: $transaction->source ?: '—')) }}</strong></div></div>
                                            @if($hasOrderSlip)
                                                <div class="col-12">
                                                    <div class="transfer-detail-section p-3">
                                                        <div class="small text-muted mb-2">{{ $orderSlipLabel }}</div>
                                                        @if($orderSlipIsImage)
                                                            <button type="button" class="sold-order-image-preview" data-image-url="{{ $orderSlipUrl }}" data-image-alt="{{ $orderSlipLabel }} for {{ $sale->order_number }}">
                                                                <img src="{{ $orderSlipUrl }}" alt="{{ $orderSlipLabel }} for {{ $sale->order_number }}" loading="lazy">
                                                                <span class="d-block small text-primary mt-2"><i class="fa-solid fa-magnifying-glass-plus me-1"></i>Click to preview</span>
                                                            </button>
                                                        @else
                                                            <a href="{{ $orderSlipUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-up-right-from-square me-1"></i>Open attachment</a>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isReturnGroup)
                                    <div class="col-12">
                                        <div class="text-uppercase small fw-bold text-primary mb-2">Return record</div>
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Channel</div><strong>{{ $transaction->channel ? ucwords(str_replace('_', ' ', $transaction->channel)) : '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isRestockGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Receiving Store Hub</div><strong>{{ $transaction->storeHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Added From</div><strong>{{ $transaction->source ? ucwords(str_replace('_', ' ', $transaction->source)) : '—' }}</strong></div></div>
                                            @if($transaction->sourceHub)
                                                <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Source Store Hub</div><strong>{{ $transaction->sourceHub->name }}</strong></div></div>
                                            @endif
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @elseif($isTransferGroup)
                                    <div class="col-12">
                                        <div class="row g-2">
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Date</div><strong>{{ $transaction->occurred_on->format('M d, Y') }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Recorded by</div><strong>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Source Hub</div><strong>{{ $transaction->sourceHub?->name ?? '—' }}</strong></div></div>
                                            <div class="col-md-6"><div class="transfer-detail-section p-3 h-100"><div class="small text-muted mb-1">Destination Hub</div><strong>{{ $transaction->targetHub?->name ?? '—' }}</strong></div></div>
                                            @if($transaction->notes)
                                                <div class="col-12"><div class="transfer-detail-section p-3"><div class="small text-muted mb-1">Notes</div><div>{{ $transaction->notes }}</div></div></div>
                                            @endif
                                        </div>
                                    </div>
                                @else
                                    <div class="col-12 @if($isSoldOrder) sold-activity-context @elseif($isTransferGroup) transfer-activity-context @endif"><div class="row g-3">
                                    <div class="col-md-3"><strong>Date</strong><div>{{ $transaction->occurred_on->format('M d, Y') }}</div></div>
                                    <div class="col-md-6"><strong>Type</strong><div>{{ $typeLabel }}</div></div>
                                    <div class="col-md-6"><strong>{{ $isSoldOrder ? 'Submitted by' : 'Recorded by' }}</strong><div>{{ $displayUser?->name ?? $displayUser?->username ?? 'System' }}</div></div>
                                    <div class="col-md-6"><strong>Hub</strong><div>{{ $transaction->storeHub?->name ?? '—' }}</div></div>
                                    <div class="col-md-6"><strong>Transfer</strong><div>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '—' }}</div></div>
                                    <div class="col-md-6"><strong>Channel / Source</strong><div>{{ $transaction->channel ?: $transaction->source ?: '—' }}</div></div>
                                    @if(!$isReturnGroup)
                                        <div class="col-md-6"><strong>Condition</strong><div>{{ $transaction->condition ? ucfirst($transaction->condition) : '—' }}</div></div>
                                    @endif
                                    <div class="col-12"><strong>Reference / Notes</strong><div>{{ $transaction->reference ?: '—' }}{{ $transaction->notes ? ' — '.$transaction->notes : '' }}</div></div>
                                    </div></div>
                                @endif
                            @endif
                        </div></div>
                    </div></div>
                </div>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-5">No transaction logs found.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $transactions->links() }}</div>
    </div>
</div>
<dialog class="inventory-order-image-viewer" id="inventoryOrderImageViewer" aria-label="Order attachment preview">
    <button type="button" class="inventory-order-image-viewer-close" aria-label="Close image preview">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img alt="">
    <div class="inventory-order-image-viewer-caption"></div>
</dialog>
@endsection

@push('scripts')
<script src="{{ asset('js/fully-booked-pullout.js') }}?v={{ filemtime(public_path('js/fully-booked-pullout.js')) }}"></script>
<script>
    (() => {
        const viewer = document.getElementById('inventoryOrderImageViewer');
        const image = viewer?.querySelector('img');
        const caption = viewer?.querySelector('.inventory-order-image-viewer-caption');
        const closeButton = viewer?.querySelector('.inventory-order-image-viewer-close');
        if (!viewer || !image || !caption || !closeButton) return;

        document.addEventListener('click', event => {
            const trigger = event.target.closest('.sold-order-image-preview');
            if (!trigger) return;

            image.src = trigger.dataset.imageUrl;
            image.alt = trigger.dataset.imageAlt;
            caption.textContent = trigger.dataset.imageAlt;
            viewer.showModal();
        });
        closeButton.addEventListener('click', () => viewer.close());
        viewer.addEventListener('click', event => {
            if (event.target === viewer) viewer.close();
        });
        viewer.addEventListener('close', () => {
            image.removeAttribute('src');
            image.alt = '';
            caption.textContent = '';
        });
    })();
</script>
@endpush
