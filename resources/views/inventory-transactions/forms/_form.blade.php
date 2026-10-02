@php
    $labels = [
        'stock_transfer' => ['button' => 'Save Transfer', 'source' => 'Sending store / source', 'channel' => false],
        'sponsor_workshop' => ['button' => 'Save Sponsor / Workshop', 'source' => 'Event or recipient', 'channel' => false],
        'restock' => ['button' => 'Save Restock', 'source' => 'Warehouse HO / Dapitan / Supplier / Store', 'channel' => false],
        'return' => ['button' => 'Save Return', 'source' => 'Customer / marketplace', 'channel' => true],
    ][$type];
@endphp

<form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required>
            @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
        </select></div>
        <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">Quantity</label><input type="number" name="quantity" min="1" class="form-control" required></div>
        <div class="col-md-8"><label class="form-label">Product</label><select name="product_id" class="form-select" required>
            @foreach($products as $product)<option value="{{ $product->id }}">{{ $product->item_id }} — {{ $product->name }} (stock: {{ $product->stock }})</option>@endforeach
        </select></div>
        <div class="col-md-4"><label class="form-label">{{ $labels['source'] }}</label><input name="source" class="form-control"></div>
        @if($type === 'stock_transfer')
            <div class="col-md-4"><label class="form-label">Target Store</label><select name="target_hub_id" class="form-select" required><option value="">Select target store</option>
                @foreach($hubs as $hub)<option value="{{ $hub->id }}">{{ $hub->name }}</option>@endforeach
            </select></div>
        @endif
        @if($labels['channel'])
            <div class="col-md-4"><label class="form-label">Return Channel</label><select name="channel" class="form-select"><option value="">Select channel</option><option value="shopee">Shopee</option><option value="lazada">Lazada</option><option value="tiktok">TikTok</option><option value="fully_booked">Fully Booked</option></select></div>
            <div class="col-md-4"><label class="form-label">Condition</label><select name="condition" class="form-select" required><option value="">Select condition</option><option value="good">Good</option><option value="damaged">Damaged</option></select></div>
        @endif
        <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Document / order no."></div>
        <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    </div>
    <div class="mt-4"><button class="btn btn-primary">{{ $labels['button'] }}</button>
        <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
