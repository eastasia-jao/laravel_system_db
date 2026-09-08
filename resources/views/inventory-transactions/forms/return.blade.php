@extends('layouts.app')

@section('content')
<style>
    #returnItems { max-height: 430px; overflow-y: auto; overflow-x: hidden; padding: .25rem .5rem .25rem 0; border: 1px solid #dee2e6; border-radius: .375rem; }
</style>
<div class="p-5">
    <h3 class="fw-bold mb-1">Return Items</h3>
    <p class="text-muted small mb-4">Record multiple returned products and classify each item as good or damaged.</p>
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" name="type" value="return">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required onchange="window.location.href='{{ route('inventory-transactions.return.create') }}?hub_id='+this.value">
                @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === (int) $hub->id)>{{ $hub->name }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><label class="form-label">Return Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Return Channel</label><select name="channel" class="form-select" required>
                <option value="">Select channel</option><option value="shopee">Shopee</option><option value="lazada">Lazada</option><option value="tiktok">TikTok</option><option value="online">Online / Walk-in</option><option value="wholesale">Wholesale</option><option value="fully_booked">Fully Booked</option>
            </select></div>
            <div class="col-md-4"><label class="form-label">Customer / Marketplace</label><input name="source" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Order / return no."></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="mb-0">Returned Items</h5><button type="button" class="btn btn-outline-primary btn-sm" id="addReturnItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>
        <div id="returnItems">
            <div class="row g-2 align-items-end return-item mb-2">
                <div class="col-md-7"><label class="form-label">Product</label><input type="search" class="form-control product-search" list="returnProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required><input type="hidden" name="items[0][product_id]" class="product-id"></div>
                <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" name="items[0][quantity]" min="1" class="form-control" required></div>
                <div class="col-md-2"><label class="form-label">Condition</label><select name="items[0][condition]" class="form-select" required><option value="">Select</option><option value="good">Good</option><option value="damaged">Bad / Damaged</option></select></div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-return-item" disabled><i class="fa-solid fa-trash"></i></button></div>
            </div>
        </div>
        <div class="mt-3"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        <div class="mt-4"><button class="btn btn-primary">Save Return Items</button><a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary ms-1">Cancel</a></div>
    </form>
</div>
@endsection

@push('scripts')
@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'barcode' => $product->barcode,
        'description' => $product->description ?: $product->name,
        'label' => $product->item_id.' — '.$product->name.($product->barcode ? ' · '.$product->barcode : '').' (stock: '.$product->stock.')',
    ])->values();
@endphp
<script>
(() => {
    const container = document.getElementById('returnItems');
    const productOptions = @json($productOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'returnProductOptions';
    productOptions.forEach(product => { const option = document.createElement('option'); option.value = `${product.label} | ${product.barcode || ''} | ${product.description}`; dataList.appendChild(option); });
    document.body.appendChild(dataList);
    let itemIndex = 1;
    const bindSearch = row => {
        const input = row.querySelector('.product-search'), hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item => `${item.label} | ${item.barcode || ''} | ${item.description}`.toLowerCase() === value);
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };
    bindSearch(container.querySelector('.return-item'));
    document.getElementById('addReturnItem').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end return-item mb-2';
        row.innerHTML = `<div class="col-md-7"><input type="search" class="form-control product-search" list="returnProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required><input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div><div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" required></div><div class="col-md-2"><select name="items[${itemIndex}][condition]" class="form-select" required><option value="">Select</option><option value="good">Good</option><option value="damaged">Bad / Damaged</option></select></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-return-item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row); bindSearch(row); itemIndex++;
    });
    container.addEventListener('click', event => { const button = event.target.closest('.remove-return-item'); if (button && !button.disabled) button.closest('.return-item').remove(); });
})();
</script>
@endpush
