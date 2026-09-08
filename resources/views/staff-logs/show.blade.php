@extends('layouts.app')

@section('content')
@php
    $actionLabels = [
        'product_import' => ['Product Import', 'success', 'fa-file-import'],
        'product_export' => ['Product Export', 'primary', 'fa-file-export'],
        'inventory_verification' => ['Inventory Verification', 'warning', 'fa-clipboard-check'],
    ];
    $config = $actionLabels[$staffLog->action_type] ?? ['Activity', 'secondary', 'fa-clock'];
    $details = $staffLog->details ?? [];
@endphp

<div class="container-fluid py-2">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <a href="{{ route('staff-logs.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>Back to Staff Logs</a>
            <h2 class="fw-bold mt-2 mb-1"><i class="fa-solid {{ $config[2] }} text-{{ $config[1] }} me-2"></i>{{ $config[0] }} Item List</h2>
            <p class="text-muted mb-0">{{ $staffLog->description }}</p>
        </div>
        <span class="badge text-bg-{{ $config[1] }} fs-6 px-3 py-2">{{ $items->total() }} item{{ $items->total() === 1 ? '' : 's' }}</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted d-block">Staff Member</small><strong>{{ $staffLog->user?->name ?? 'Deleted user' }}</strong><div class="small text-muted">{{ str_replace('_', ' ', $staffLog->user?->role ?? 'unknown') }}</div></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted d-block">Store Hub</small><strong>{{ $staffLog->storeHub?->name ?? 'N/A' }}</strong></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted d-block">Date & Time</small><strong>{{ $staffLog->created_at->format('M d, Y') }}</strong><div class="small text-muted">{{ $staffLog->created_at->format('h:i:s A') }}</div></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted d-block">Source</small><strong>{{ $details['file_name'] ?? ($details['invoice_number'] ?? 'Inventory system') }}</strong><div class="small text-muted">IP: {{ $staffLog->ip_address ?? 'N/A' }}</div></div></div></div>
    </div>

    @if($staffLog->action_type === 'product_import')
        <div class="alert alert-light border d-flex gap-4 mb-4">
            <span><strong class="text-success">{{ $details['created_count'] ?? 0 }}</strong> created</span>
            <span><strong class="text-info">{{ $details['updated_count'] ?? 0 }}</strong> updated</span>
            @if(($details['skipped_count'] ?? 0) > 0)<span><strong class="text-warning">{{ $details['skipped_count'] }}</strong> skipped</span>@endif
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 pt-3">
            <form method="GET" class="d-flex gap-2" style="max-width: 500px;">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search item ID or product name...">
                <button class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                @if(request()->filled('search'))<a href="{{ route('staff-logs.show', $staffLog) }}" class="btn btn-outline-secondary">Clear</a>@endif
            </form>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Item ID</th>
                            <th>Product Name</th>
                            @if($staffLog->action_type === 'product_import')<th>Import Result</th>@endif
                            @if($staffLog->action_type === 'inventory_verification')<th class="text-center">Quantity Deducted</th>@endif
                            @if($staffLog->action_type === 'product_export')<th class="text-center">Exported Stock</th>@endif
                            <th class="text-center">Stock Before</th>
                            <th class="text-center pe-4">Stock After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $item->item_id ?? 'N/A' }}</td>
                                <td>{{ $item->product_name }}</td>
                                @if($staffLog->action_type === 'product_import')
                                    <td><span class="badge {{ $item->operation === 'created' ? 'bg-success' : 'bg-info text-dark' }}">{{ strtoupper($item->operation ?? 'updated') }}</span></td>
                                @endif
                                @if($staffLog->action_type === 'inventory_verification')<td class="text-center fw-bold text-danger">-{{ $item->quantity ?? 0 }}</td>@endif
                                @if($staffLog->action_type === 'product_export')<td class="text-center">{{ $item->stock_before ?? 0 }}</td>@endif
                                <td class="text-center">{{ $item->stock_before ?? '—' }}</td>
                                <td class="text-center pe-4">{{ $item->stock_after ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No matching item details found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
