@extends('layouts.app')

@section('content')
@php($canEditAllocations = auth()->user()->can('manage-inventory') && $hub)
<div class="p-5">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Sales Stock Allocation</h3>
            <p class="text-muted small mb-0">Allocate available physical stock by sales channel and review remaining quantities.</p>
        </div>
        @if($canEditAllocations)
            <button form="allocation-form" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-2"></i>Save Allocations
            </button>
        @endif
    </div>
    @if(auth()->user()->can('manage-inventory') && ! $hub)
        <div class="alert alert-info">Select a store hub before editing allocations.</div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4">
        <form method="GET" action="{{ route('stock-allocation.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small text-muted fw-bold">STORE HUB</label>
                <select name="hub_id" class="form-select" onchange="this.form.submit()">
                    <option value="">All stores</option>
                    @foreach($hubs as $storeHub)
                        <option value="{{ $storeHub->id }}" @selected($hub?->id === $storeHub->id)>{{ strtoupper($storeHub->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="small text-muted fw-bold">SEARCH</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Product name, item ID, or barcode">
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-primary w-100">Search</button>
            </div>
        </form>
    </div>

    <form id="allocation-form" method="POST" action="{{ route('stock-allocation.update') }}">
        @csrf
        <input type="hidden" name="hub_id" value="{{ $hub?->id ?? request('hub_id') }}">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th>Product</th>
                            <th class="text-end">Physical Stock</th>
                            <th>Online / Event / Restock</th>
                            <th>Wholesale</th>
                            <th>Shopee</th>
                            <th>Lazada</th>
                            <th>TikTok</th>
                            <th class="text-end">Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $product->name }}</div>
                                <small class="text-muted">{{ $product->item_id }}{{ $product->barcode ? ' · '.$product->barcode : '' }}</small>
                            </td>
                            <td class="text-end fw-semibold">{{ $product->stock }}</td>
                            @foreach(['online', 'wholesale', 'shopee', 'lazada', 'tiktok'] as $channel)
                                <td>
                                    @if($canEditAllocations)
                                        <input type="number" min="0" max="{{ $product->starting_inventory }}" class="form-control form-control-sm"
                                            name="allocations[{{ $product->id }}][{{ $channel }}]"
                                            value="{{ $product->allocation_values[$channel] }}">
                                    @else
                                        <span>{{ in_array($channel, $assignedChannels, true) ? $product->remaining_values[$channel] : '—' }}</span>
                                    @endif
                                    @if($product->sold_values->get($channel, 0) > 0)
                                        <small class="text-muted d-block">Sold: {{ $product->sold_values[$channel] }}</small>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end fw-bold text-success">{{ $product->remaining_values->sum() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-5">No products found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $products->links() }}</div>
        </div>
    </form>
</div>
@endsection
