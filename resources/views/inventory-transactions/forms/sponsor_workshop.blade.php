@extends('layouts.app')

@section('content')
<style>
    #sponsorItems {
        max-height: 430px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.25rem 0.5rem 0.25rem 0;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
</style>
<div class="p-5">
    <h3 class="fw-bold mb-1">Sponsor / Workshop Items</h3>
    <p class="text-muted small mb-4">Record multiple items used for sponsorships, workshops, or events.</p>
    @if($errors->any())
        <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i>{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" name="type" value="sponsor_workshop">
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Store Hub</label><select name="store_hub_id" class="form-select" required onchange="window.location.href='{{ route('inventory-transactions.sponsor.create') }}?hub_id='+this.value">
                @foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>@endforeach
            </select></div>
            <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
            <div class="col-md-4"><label class="form-label">Event / Recipient</label><input name="source" class="form-control" required></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="mb-0">Items to Pull Out</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addSponsorItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>
        <div id="sponsorItems">
            <div class="row g-2 align-items-end sponsor-item mb-2">
                <div class="col-md-9"><label class="form-label">Product</label><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                    <input type="hidden" name="items[0][product_id]" class="product-id"></div>
                <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" name="items[0][quantity]" min="1" class="form-control" required></div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-sponsor-item" disabled><i class="fa-solid fa-trash"></i></button></div>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Document / event no."></div>
            <div class="col-md-8"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Save Sponsor / Workshop</button>
            <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@php
    $productOptions = $products->map(function ($product) {
        return [
            'id' => $product->id,
            'item_id' => $product->item_id,
            'barcode' => $product->barcode,
            'description' => $product->description ?: $product->name,
            'label' => $product->item_id.' — '.$product->name.($product->barcode ? ' · '.$product->barcode : '').' (stock: '.$product->stock.')',
        ];
    })->values();
@endphp
<script>
(() => {
    const container = document.getElementById('sponsorItems');
    const addButton = document.getElementById('addSponsorItem');
    const productOptions = @json($productOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'sponsorProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = `${product.label} | ${product.barcode || ''} | ${product.description}`;
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);
    let itemIndex = 1;

    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item =>
                `${item.label} | ${item.barcode || ''} | ${item.description}`.toLowerCase() === value
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };

    bindProductSearch(container.querySelector('.sponsor-item'));
    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end sponsor-item mb-2';
        row.innerHTML = `<div class="col-md-9"><input type="search" class="form-control product-search" list="sponsorProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
            <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-sponsor-item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        itemIndex++;
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('.remove-sponsor-item');
        if (button && !button.disabled) button.closest('.sponsor-item').remove();
    });
})();
</script>
@endpush
