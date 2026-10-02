@extends('layouts.app')

@section('content')
<div class="p-5">
    <h3 class="fw-bold mb-1">Record Inventory Transaction</h3>
    <p class="text-muted small mb-4">Use this form for transfers, sponsor/workshop usage, restocks, and returns.</p>
    
    <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Transaction Type</label><select name="type" id="transactionType" class="form-select" required>
                <option value="stock_transfer">Stock Transfer (HO to Store)</option><option value="sponsor_workshop">Sponsor / Workshop</option><option value="restock">Restock / Added from Request (Warehouse HO)</option><option value="return">Return Items</option>
            </select></div>
            <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required>
                @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-8"><label class="form-label">Product</label><select name="product_id" class="form-select" required>
                @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->item_id }} — {{ $product->name }} (stock: {{ $product->stock }})</option>@endforeach
            </select></div>
            <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" name="quantity" min="1" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Channel</label><select name="channel" class="form-select">
                <option value="">—</option><option value="shopee">Shopee</option><option value="lazada">Lazada</option><option value="tiktok">TikTok</option><option value="online">Online / Walk-in</option><option value="wholesale">Wholesale</option><option value="fully_booked">Fully Booked</option>
            </select></div>
            <div class="col-md-4"><label class="form-label">Source / Reason</label><input name="source" class="form-control" placeholder="Warehouse HO / Dapitan / Supplier / Store"></div>
            <div class="col-md-4"><label class="form-label">Condition (returns)</label><select name="condition" class="form-select"><option value="">—</option><option value="good">Good</option><option value="damaged">Damaged</option></select></div>
            <div class="col-md-4"><label class="form-label">Target Store (transfer)</label><select name="target_hub_id" class="form-select"><option value="">—</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}">{{ $hub->name }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Document / order no."></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Save Transaction</button> <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a></div>
    </form>
</div>
@endsection
