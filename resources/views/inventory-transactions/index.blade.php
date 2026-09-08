@extends('layouts.app')

@section('content')
<style>
    .inventory-log-table {
        font-size: .875rem;
    }
    .inventory-log-table th,
    .inventory-log-table td {
        padding: .55rem .65rem;
        white-space: nowrap;
    }
    .inventory-log-table td:nth-child(3),
    .inventory-log-table td:nth-child(8) {
        white-space: normal;
    }
</style>
<div class="p-5">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h3 class="fw-bold">Inventory Transaction Logs</h3>
            <p class="text-muted small mb-0">
                @if(auth()->user()?->role === 'sales_associate')
                    Branch-to-branch stock transfers for {{ $hubs->first()?->name ?? 'your designated branch' }}.
                @else
                    Monthly item movement for sold items, transfers, sponsor/workshop, restocks, and returns.
                @endif
            </p>
        </div>
    </div>
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @can('manage-inventory')
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-5 g-3 mb-4">
            @foreach([
                ['route' => 'inventory-transactions.transfer.create', 'icon' => 'fa-truck-ramp-box', 'title' => 'Stock Transfer', 'text' => 'HO to branch'],
                ['route' => 'inventory-transactions.branch-transfer.create', 'icon' => 'fa-right-left', 'title' => 'Stock Transfer', 'text' => 'BRANCH to BRANCH'],
                ['route' => 'inventory-transactions.sponsor.create', 'icon' => 'fa-people-group', 'title' => 'Sponsor / Workshop', 'text' => 'Event usage'],
                ['route' => 'inventory-transactions.restock.create', 'icon' => 'fa-boxes-stacked', 'title' => 'Restock / Added Items', 'text' => 'Warehouse request or branch transfer'],
                ['route' => 'inventory-transactions.return.create', 'icon' => 'fa-rotate-left', 'title' => 'Return Items', 'text' => 'Good or damaged'],
            ] as $action)
                <div class="col"><a href="{{ route($action['route'], ['hub_id' => $hubId]) }}" class="card border-0 shadow-sm rounded-4 p-3 text-decoration-none h-100">
                    <i class="fa-solid {{ $action['icon'] }} text-primary fs-4 mb-2"></i><strong class="text-dark">{{ $action['title'] }}</strong><small class="text-muted">{{ $action['text'] }}</small>
                </a></div>
            @endforeach
        </div>
    @endcan
    @can('manage-branch-transfers')
        @cannot('manage-inventory')
            <div class="row row-cols-1 row-cols-md-2 g-3 mb-4">
                <div class="col">
                    <a href="{{ route('inventory-transactions.branch-transfer.create', ['hub_id' => $hubId]) }}" class="card border-0 shadow-sm rounded-4 p-3 text-decoration-none h-100">
                        <i class="fa-solid fa-right-left text-primary fs-4 mb-2"></i>
                        <strong class="text-dark">Stock Transfer</strong>
                        <small class="text-muted">BRANCH to BRANCH</small>
                    </a>
                </div>
            </div>
        @endcannot
    @endcan
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form class="row g-2 align-items-end">
            <div class="col-md-3"><label class="small fw-bold text-muted">STORE HUB</label>
                <select name="hub_id" class="form-select"><option value="">All stores</option>
                    @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="small fw-bold text-muted">LOG TYPE</label>
                <select name="type" class="form-select">
                    @if(auth()->user()?->role !== 'sales_associate')<option value="">All types</option>@endif
                    @foreach(auth()->user()?->role === 'sales_associate'
                        ? ['branch_transfer' => 'Stock Transfer (BRANCH to BRANCH)']
                        : ['sold'=>'Sold Items','stock_transfer'=>'Stock Transfer (HO to BRANCH)','branch_transfer'=>'Stock Transfer (BRANCH to BRANCH)','sponsor_workshop'=>'Sponsor / Workshop','restock'=>'Restock / Added Items','return'=>'Return Items'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><label class="small fw-bold text-muted">MONTH</label><input type="month" name="month" value="{{ request('month') }}" class="form-control"></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Filter</button></div>
        </form>
    </div>
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0 inventory-log-table">
            <thead class="table-light small text-uppercase"><tr><th>Date</th><th>Type</th><th>Item</th><th>Hub</th><th>Channel / Source</th><th>Condition</th><th class="text-end">Qty</th><th>Transfer</th><th>User</th><th>Actions</th></tr></thead>
            <tbody>
            @forelse($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->occurred_on->format('M d, Y') }}</td>
                    <td>{{ $transaction->type === 'branch_transfer' ? 'Stock Transfer (BRANCH to BRANCH)' : ucwords(str_replace('_', ' ', $transaction->type)) }}</td>
                    <td><strong>{{ $transaction->product->name }}</strong><small class="d-block text-muted">{{ $transaction->product->item_id }}</small></td>
                    <td>{{ $transaction->storeHub->name }}</td>
                    <td>
                        @if($transaction->channel)
                            {{ ucwords(str_replace('_', ' ', $transaction->channel)) }}
                        @elseif($transaction->source === 'stock_transfer')
                            Stock Transfer
                        @elseif($transaction->source === 'warehouse_request')
                            Warehouse Request
                        @else
                            {{ $transaction->source ?: '—' }}
                        @endif
                    </td>
                    <td>{{ $transaction->condition ? ucfirst($transaction->condition) : '—' }}</td>
                    <td class="text-end fw-bold">{{ $transaction->quantity }}</td>
                    <td>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '' }}</td>
                    <td>{{ $transaction->creator?->name ?? $transaction->creator?->username ?? 'System' }}</td>
                    <td><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#transactionModal{{ $transaction->id }}"><i class="fa-solid fa-eye me-1"></i>View</button></td>
                </tr>
                <div class="modal fade" id="transactionModal{{ $transaction->id }}" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Transaction Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            <div class="col-md-6"><strong>Product</strong><div>{{ $transaction->product->name }} ({{ $transaction->product->item_id }})</div></div>
                            <div class="col-md-3"><strong>Quantity</strong><div>{{ $transaction->quantity }}</div></div>
                            <div class="col-md-3"><strong>Date</strong><div>{{ $transaction->occurred_on->format('M d, Y') }}</div></div>
                            <div class="col-md-6"><strong>Type</strong><div>{{ $transaction->type === 'branch_transfer' ? 'Stock Transfer (BRANCH to BRANCH)' : ucwords(str_replace('_', ' ', $transaction->type)) }}</div></div>
                            <div class="col-md-6"><strong>Recorded by</strong><div>{{ $transaction->creator?->name ?? $transaction->creator?->username ?? 'System' }}</div></div>
                            <div class="col-md-6"><strong>Hub</strong><div>{{ $transaction->storeHub->name }}</div></div>
                            <div class="col-md-6"><strong>Transfer</strong><div>{{ $transaction->sourceHub?->name }}{{ $transaction->targetHub ? ' → '.$transaction->targetHub->name : '—' }}</div></div>
                            <div class="col-md-6"><strong>Channel / Source</strong><div>{{ $transaction->channel ?: $transaction->source ?: '—' }}</div></div>
                            <div class="col-md-6"><strong>Condition</strong><div>{{ $transaction->condition ? ucfirst($transaction->condition) : '—' }}</div></div>
                            <div class="col-12"><strong>Reference / Notes</strong><div>{{ $transaction->reference ?: '—' }}{{ $transaction->notes ? ' — '.$transaction->notes : '' }}</div></div>
                        </div></div>
                    </div></div>
                </div>
            @empty <tr><td colspan="10" class="text-center text-muted py-5">No transaction logs found.</td></tr> @endforelse
            </tbody>
        </table></div>
        <div class="p-3">{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
