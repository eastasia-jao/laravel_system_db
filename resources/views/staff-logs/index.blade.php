@extends('layouts.app')

@section('content')
<style>
    .staff-log-page { --log-ink: #172033; --log-muted: #64748b; --log-border: #e2e8f0; }
    .staff-log-hero { position: relative; overflow: hidden; border: 0; border-radius: 20px; padding: 1.5rem 1.75rem; color: #fff; background: linear-gradient(120deg, #172554, #1d4ed8 58%, #38bdf8); box-shadow: 0 16px 34px rgba(30, 64, 175, .16); }
    .staff-log-hero::after { position: absolute; top: -95px; right: 8%; width: 260px; height: 260px; border: 1px solid rgba(255,255,255,.12); border-radius: 50%; content: ""; box-shadow: 0 0 0 28px rgba(255,255,255,.04), 0 0 0 58px rgba(255,255,255,.035); pointer-events: none; }
    .staff-log-hero-content { position: relative; z-index: 1; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .staff-log-hero-icon { display: inline-flex; width: 52px; height: 52px; flex: 0 0 52px; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,.25); border-radius: 16px; color: #fff; background: rgba(255,255,255,.16); font-size: 1.2rem; }
    .staff-log-hero h2 { color: #fff; font-size: clamp(1.35rem, 2vw, 1.8rem); letter-spacing: -.025em; }
    .staff-log-hero p { color: rgba(255,255,255,.82); }
    .staff-log-total { position: relative; z-index: 1; display: inline-flex; align-items: center; gap: .45rem; padding: .55rem .8rem; border: 1px solid rgba(255,255,255,.25); border-radius: 12px; color: #fff; background: rgba(15,23,42,.22); font-size: .85rem; font-weight: 700; white-space: nowrap; }
    .staff-log-filter-card, .staff-log-table-card { border: 1px solid var(--log-border); border-radius: 16px; background: #fff; box-shadow: 0 5px 18px rgba(15,23,42,.045); }
    .staff-log-filter-card { overflow: visible; }
    .staff-log-table-card { overflow: hidden; }
    .staff-log-filter-card .card-body { padding: 1.1rem; }
    .staff-log-filter-grid { display: grid; grid-template-columns: minmax(220px, 1.6fr) repeat(5, minmax(130px, 1fr)) auto; gap: .85rem; align-items: end; }
    .staff-log-filter-grid .filter-search { grid-column: span 1; }
    .staff-log-filter-grid .form-label { margin-bottom: .4rem; color: #475569; font-size: .74rem; font-weight: 700; letter-spacing: .035em; }
    .staff-log-filter-grid .form-control, .staff-log-filter-grid .form-select { min-height: 42px; border-color: #dbe3ee; border-radius: 9px; color: var(--log-ink); font-size: .88rem; }
    .staff-log-filter-grid .form-control:focus, .staff-log-filter-grid .form-select:focus { border-color: #60a5fa; box-shadow: 0 0 0 .2rem rgba(59,130,246,.12); }
    .staff-log-scroll-select .dropdown-toggle { min-height: 42px; border-color: #dbe3ee; border-radius: 9px; color: var(--log-ink); background: #fff; font-size: .88rem; text-align: left; }
    .staff-log-scroll-select .dropdown-toggle::after { position: absolute; top: 50%; right: .9rem; transform: translateY(-50%); }
    .staff-log-scroll-select .dropdown-menu { width: 100%; max-height: 220px; overflow-y: auto; overflow-x: hidden; scrollbar-gutter: stable; }
    .staff-log-scroll-select .dropdown-item { white-space: normal; font-size: .88rem; }
    .staff-log-scroll-select .dropdown-item.active, .staff-log-scroll-select .dropdown-item:active { color: #fff; background: #2563eb; }
    .staff-log-filter-actions { display: flex; gap: .45rem; }
    .staff-log-filter-actions .btn { min-height: 42px; border-radius: 9px; }
    .staff-log-table-card .table-responsive { overflow-x: auto; }
    .staff-log-table { min-width: 1080px; margin: 0; table-layout: fixed; color: var(--log-ink); }
    .branch-transfer-log-table { min-width: 1240px; }
    .staff-log-table th { padding: .9rem .75rem; border-bottom: 1px solid #dbe3ee; color: #526178; background: #f8fafc; font-size: .7rem; font-weight: 800; letter-spacing: .07em; text-transform: uppercase; white-space: nowrap; }
    .staff-log-table td { padding: .95rem .75rem; border-color: #edf1f6; font-size: .86rem; vertical-align: middle; }
    .staff-log-table tbody tr { transition: background-color .15s ease; }
    .staff-log-table tbody tr:hover { background: #f8fbff; }
    .staff-log-table .log-date-cell { color: #334155; white-space: nowrap; }
    .staff-log-table .log-date-cell small, .staff-log-table .log-staff-role { display: block; margin-top: .2rem; color: var(--log-muted); font-size: .75rem; }
    .staff-log-table .log-staff-name { font-weight: 700; overflow-wrap: anywhere; }
    .staff-log-table .log-hub-cell { color: #334155; font-weight: 600; overflow-wrap: anywhere; }
    .staff-log-table .log-summary-cell { color: #334155; line-height: 1.5; overflow-wrap: anywhere; }
    .staff-log-table .log-activity-badge { display: inline-flex; align-items: center; gap: .35rem; max-width: 100%; border-radius: 7px; padding: .38rem .55rem; font-size: .73rem; font-weight: 700; white-space: normal; }
    .staff-log-table .log-status-badge { display: inline-block; margin-top: .35rem; border-radius: 6px; padding: .25rem .45rem; font-size: .68rem; font-weight: 800; letter-spacing: .025em; }
    .staff-log-table .log-items-cell { text-align: center; }
    .staff-log-table .log-items-cell .btn { border-radius: 8px; font-size: .78rem; }
    .staff-log-empty { padding: 3.5rem 1rem !important; color: var(--log-muted) !important; text-align: center; }
    .staff-log-empty i { display: block; margin-bottom: .8rem; color: #94a3b8; font-size: 1.8rem; }
    .staff-log-pagination { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: 1rem 1.15rem; border-top: 1px solid #edf1f6; }
    .staff-log-pagination .pagination { margin-bottom: 0; }
    @media (max-width: 1399.98px) { .staff-log-filter-grid { grid-template-columns: repeat(auto-fit, minmax(165px, 1fr)); } .staff-log-filter-grid .filter-search { grid-column: span 2; } }
    @media (max-width: 767.98px) {
        .staff-log-hero { padding: 1.2rem; border-radius: 16px; }
        .staff-log-hero-content { align-items: flex-start; gap: .75rem; }
        .staff-log-hero-icon { width: 44px; height: 44px; flex-basis: 44px; border-radius: 13px; }
        .staff-log-hero { align-items: flex-start !important; }
        .staff-log-total { margin-left: 3.25rem; }
        .staff-log-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .staff-log-filter-grid .filter-search { grid-column: 1 / -1; }
        .staff-log-filter-grid .filter-actions-wrap { grid-column: 1 / -1; }
        .staff-log-filter-actions .btn { flex: 1; }
        .staff-log-pagination { align-items: flex-start; }
    }
</style>
@php
    $isSalesAssociate = auth()->user()->role === 'sales_associate';
    $actionLabels = $isSalesAssociate ? [
        'branch_transfer_sent' => ['Branch Transfer', 'success', 'fa-truck-ramp-box'],
    ] : [
        'branch_transfer_sent' => ['Branch Transfer', 'success', 'fa-truck-ramp-box'],
        'catalog_assignment' => ['Catalog Assignment', 'info', 'fa-book'],
        'product_import' => ['Product Import', 'success', 'fa-file-import'],
        'product_export' => ['Product Export', 'primary', 'fa-file-export'],
        'inventory_verification' => ['Inventory Verification', 'warning', 'fa-clipboard-check'],
    ];
    $showHubFilter = $showHubFilter ?? true;
@endphp

<div class="container-fluid py-2 staff-log-page">
    <div class="staff-log-hero d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="staff-log-hero-content">
            <span class="staff-log-hero-icon"><i class="fa-solid {{ $isSalesAssociate ? 'fa-arrow-right-arrow-left' : 'fa-clock-rotate-left' }}"></i></span>
            <div>
                <h2 class="fw-bold mb-1">{{ $isSalesAssociate ? 'Branch Transfer Logs' : 'Staff Activity Logs' }}</h2>
                <p class="mb-0">{{ $isSalesAssociate ? 'Branch-to-branch transfer requests from your assigned branches.' : 'Product imports, exports, catalog assignments, and inventory verification history.' }}</p>
            </div>
        </div>
        <span class="staff-log-total"><i class="fa-solid fa-list-check"></i>{{ number_format($logs->total()) }} {{ $logs->total() === 1 ? 'log' : 'logs' }}</span>
    </div>

    <div class="card staff-log-filter-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('staff-logs.index') }}" class="staff-log-filter-grid">
                <div class="filter-search">
                    <label class="form-label small fw-semibold">Search</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="search" name="search" value="{{ request('search') }}" class="form-control border-start-0 ps-0" placeholder="{{ $isSalesAssociate ? 'Transfer document, item...' : 'Staff, item, invoice...' }}">
                    </div>
                </div>
                @unless($isSalesAssociate)
                <div>
                    <label class="form-label small fw-semibold">Activity</label>
                    <select name="action" class="form-select js-staff-log-scroll-select">
                        <option value="">All activities</option>
                        @foreach($actionLabels as $value => $config)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $config[0] }}</option>
                        @endforeach
                    </select>
                </div>
                @endunless
                @if($showHubFilter)
                <div>
                    <label class="form-label small fw-semibold">Store Hub</label>
                    <select name="hub_id" class="form-select js-staff-log-scroll-select">
                        <option value="">All hubs</option>
                        @foreach($hubs as $hub)
                            <option value="{{ $hub->id }}" @selected((string) request('hub_id') === (string) $hub->id)>{{ $hub->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div>
                    <label class="form-label small fw-semibold">{{ $isSalesAssociate ? 'Sent By' : 'Staff Member' }}</label>
                    <select name="user_id" class="form-select js-staff-log-scroll-select">
                        <option value="">{{ $isSalesAssociate ? 'All senders' : 'All staff' }}</option>
                        @foreach($staff as $member)
                            <option value="{{ $member->id }}" @selected((string) request('user_id') === (string) $member->id)>{{ $member->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label small fw-semibold">From</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div>
                    <label class="form-label small fw-semibold">To</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="filter-actions-wrap">
                    <label class="form-label small fw-semibold d-block">Actions</label>
                    <div class="staff-log-filter-actions">
                        <button class="btn btn-primary" title="Apply filters" aria-label="Apply filters"><i class="fa-solid fa-filter"></i></button>
                        <a href="{{ route('staff-logs.index') }}" class="btn btn-outline-secondary" title="Clear filters" aria-label="Clear filters"><i class="fa-solid fa-rotate-left"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card staff-log-table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle staff-log-table {{ $isSalesAssociate ? 'branch-transfer-log-table' : '' }}">
                    @if($isSalesAssociate)
                        <colgroup>
                            <col style="width: 130px">
                            <col style="width: 150px">
                            <col style="width: 165px">
                            <col style="width: 155px">
                            <col>
                            <col style="width: 145px">
                        </colgroup>
                    @else
                        <colgroup>
                            <col style="width: 145px">
                            <col style="width: 155px">
                            <col style="width: 165px">
                            <col style="width: 185px">
                            <col>
                            <col style="width: 160px">
                        </colgroup>
                    @endif
                    <thead>
                        <tr>
                            <th class="ps-4 {{ $isSalesAssociate ? 'transfer-log-date' : '' }}">Date &amp; Time</th>
                            <th>{{ $isSalesAssociate ? 'Sent By' : 'Staff' }}</th>
                            <th>Store Hub</th>
                            <th>Activity</th>
                            <th>Summary</th>
                            <th class="text-center pe-4">Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            @php
                                $config = $actionLabels[$log->action_type] ?? ['Activity', 'secondary', 'fa-clock'];
                                $details = $log->details ?? [];
                            @endphp
                            <tr>
                                <td class="ps-4 log-date-cell {{ $isSalesAssociate ? 'transfer-log-date' : '' }}">
                                    <div class="fw-semibold">{{ $log->created_at->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $log->created_at->format('h:i:s A') }}</small>
                                </td>
                                <td class="{{ $isSalesAssociate ? 'transfer-log-sender' : '' }}">
                                    <div class="log-staff-name">{{ $log->user?->name ?? 'Deleted user' }}</div>
                                    <small class="log-staff-role">{{ str_replace('_', ' ', $log->user?->role ?? 'unknown') }}</small>
                                </td>
                                <td class="log-hub-cell {{ $isSalesAssociate ? 'transfer-log-hub' : '' }}">{{ $log->storeHub?->name ?? 'N/A' }}</td>
                                <td class="{{ $isSalesAssociate ? 'transfer-log-activity' : '' }}">
                                    <span class="badge text-bg-{{ ($details['status'] ?? 'approved') === 'rejected' ? 'danger' : $config[1] }} log-activity-badge">
                                        <i class="fa-solid {{ $config[2] }} me-1"></i>{{ $config[0] }}
                                    </span>
                                    @if($isSalesAssociate && !empty($details['status']))
                                        <span class="badge text-bg-{{ $details['status'] === 'rejected' ? 'danger' : 'success' }} log-status-badge">{{ strtoupper($details['status']) }}</span>
                                    @endif
                                </td>
                                <td class="log-summary-cell">
                                    <div>{{ $log->description }}</div>
                                    @if(!empty($details['file_name']))
                                        <small class="text-muted">File: {{ $details['file_name'] }}</small>
                                    @elseif(!empty($details['invoice_number']))
                                        <small class="text-muted">Invoice: {{ $details['invoice_number'] }}</small>
                                    @endif
                                </td>
                                <td class="log-items-cell pe-4 {{ $isSalesAssociate ? 'transfer-log-items' : '' }}">
                                    <a href="{{ route('staff-logs.show', ['staffLog' => $log, 'logs_page' => $logs->currentPage()]) }}" class="btn btn-sm btn-outline-primary text-nowrap">
                                        <i class="fa-solid fa-list me-1"></i> View List ({{ $log->items_count }})
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="staff-log-empty"><i class="fa-solid fa-clock-rotate-left"></i><span class="fw-semibold d-block text-dark">No activity found</span><span>Try changing your search or filters.</span></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="staff-log-pagination">
            <div class="text-muted small">
                Showing {{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} entries
            </div>
            <div>
                {{ $logs->appends(request()->query())->links() }}
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-staff-log-scroll-select').forEach(function (select, index) {
        const wrapper = document.createElement('div');
        wrapper.className = 'dropdown staff-log-scroll-select';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn dropdown-toggle w-100 position-relative pe-5';
        button.id = 'staffLogFilterDropdown' + index;
        button.setAttribute('data-bs-toggle', 'dropdown');
        button.setAttribute('aria-expanded', 'false');

        const menu = document.createElement('div');
        menu.className = 'dropdown-menu shadow-sm';
        menu.setAttribute('aria-labelledby', button.id);

        Array.from(select.options).forEach(function (option) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'dropdown-item' + (option.selected ? ' active' : '');
            item.textContent = option.textContent;
            item.dataset.value = option.value;
            item.addEventListener('click', function () {
                select.value = option.value;
                button.textContent = option.textContent;
                menu.querySelectorAll('.dropdown-item').forEach(function (candidate) {
                    candidate.classList.toggle('active', candidate === item);
                });
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
            menu.appendChild(item);
        });

        button.textContent = select.options[select.selectedIndex]?.textContent || '';
        select.classList.add('d-none');
        select.insertAdjacentElement('afterend', wrapper);
        wrapper.append(button, menu);
    });
});
</script>
@endpush
