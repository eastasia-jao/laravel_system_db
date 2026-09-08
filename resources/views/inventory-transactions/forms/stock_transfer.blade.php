@extends('layouts.app')

@section('content')
@php
    $branchTransfer = $type === 'branch_transfer';
    $transferRoute = $branchTransfer ? 'inventory-transactions.branch-transfer.create' : 'inventory-transactions.transfer.create';
@endphp
<style>
    #transferItems {
        max-height: 430px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.25rem 0.5rem 0.25rem 0;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
</style>
<div class="p-5">
    <h3 class="fw-bold mb-1">Stock Transfer{{ $branchTransfer ? ' (BRANCH to BRANCH)' : ' (HO to BRANCH)' }}</h3>
    <p class="text-muted small mb-4">Transfer multiple products from one store to another in one transaction.</p>
    @if($errors->any())
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation me-2"></i>{{ $errors->first() }}
        </div>
    @endif
    <form method="POST" action="{{ route($branchTransfer ? 'inventory-transactions.branch-transfer.store' : 'inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ $branchTransfer ? 'Source Store' : 'Source Head Office' }}</label>
                <select name="store_hub_id" id="sourceHub" class="form-select" required onchange="window.location.href='{{ route($transferRoute) }}?hub_id='+this.value">
                    @if($transferSourceHubs->isEmpty())
                        <option value="">{{ $branchTransfer ? 'No source branches available' : 'Enable Head Office Mode for an active store first' }}</option>
                    @endif
                    @foreach($transferSourceHubs as $hub)
                        <option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Target Store</label>
                <select name="target_hub_id" class="form-select" required>
                    <option value="">Select target store</option>
                    @foreach($allHubs as $hub)
                        @if((int) $hubId !== $hub->id)
                            <option value="{{ $hub->id }}">{{ $hub->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Transfer Date</label>
                <input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="mb-0">Items to Transfer</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addTransferItem">
                <i class="fa-solid fa-plus me-1"></i>Add Item
            </button>
        </div>
        <div id="transferItems">
            <div class="row g-2 align-items-end transfer-item mb-2">
                <div class="col-md-9">
                    <label class="form-label">Product</label>
                    <input type="search" class="form-control product-search" list="transferProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                    <input type="hidden" name="items[0][product_id]" class="product-id">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="items[0][quantity]" min="1" class="form-control" required>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-outline-danger remove-transfer-item" disabled><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Transfer document no."></div>
            <div class="col-md-8"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="mt-4">
            <button class="btn btn-primary">Save Transfer</button>
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
    const container = document.getElementById('transferItems');
    const addButton = document.getElementById('addTransferItem');
    const productOptions = @json($productOptions);
    let itemIndex = 1;
    const dataList = document.createElement('datalist');
    dataList.id = 'transferProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = `${product.label} | ${product.barcode || ''} | ${product.description}`;
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);

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

    bindProductSearch(container.querySelector('.transfer-item'));

    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end transfer-item mb-2';
        row.innerHTML = `
            <div class="col-md-9"><input type="search" class="form-control product-search" list="transferProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-transfer-item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        itemIndex++;
    });

    container.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-transfer-item');
        if (button && !button.disabled) button.closest('.transfer-item').remove();
    });
})();
</script>
@endpush
