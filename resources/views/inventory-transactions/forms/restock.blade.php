@extends('layouts.app')

@section('content')
<style>
    .restock-page-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border: 1px solid #dbeafe;
        border-radius: 1rem;
        background: linear-gradient(110deg, #eff6ff 0%, #fff 72%);
    }
    .restock-page-heading-icon {
        display: grid;
        flex: 0 0 3rem;
        width: 3rem;
        height: 3rem;
        place-items: center;
        border-radius: 0.875rem;
        background: #dbeafe;
        color: #2563eb;
        font-size: 1.25rem;
    }
    .restock-page-heading h3 {
        font-size: clamp(1.15rem, 2vw, 1.5rem);
    }
    @media (max-width: 575.98px) {
        .restock-page-heading {
            align-items: flex-start;
            padding: 1rem;
        }
        .restock-page-heading-icon {
            flex-basis: 2.5rem;
            width: 2.5rem;
            height: 2.5rem;
        }
    }
    #restockItems {
        max-height: 430px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.75rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    #restockItemsHeader {
        padding: 0 0.75rem 0.5rem;
        border-bottom: 1px solid #dee2e6;
    }
    #restockItems .restock-item + .restock-item {
        border-top: 1px solid #edf0f2;
        padding-top: 0.75rem;
    }
    @media (max-width: 767.98px) {
        #restockItemsHeader { display: none; }
    }
</style>
<div class="workspace-page inventory-entry-page">
    <header class="restock-page-heading mb-3">
        <span class="restock-page-heading-icon" aria-hidden="true"><i class="fa-solid fa-boxes-stacked"></i></span>
        <div>
            <div class="small text-uppercase fw-bold text-primary mb-1">Inventory Receiving</div>
            <h3 class="fw-bold mb-1">Restock / Added from Request (Warehouse HO)</h3>
            <p class="text-muted small mb-0">Record items received by Head Office from a warehouse request.</p>
        </div>
    </header>
    
    <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
        @include('inventory-transactions.forms.bulk-items')
        <input type="hidden" name="type" value="restock">
        <div class="row g-3">
        <div class="col-md-4"><label class="form-label">Receiving Store Hub</label>
            <select name="store_hub_id" class="form-select" required onchange="window.location.href='{{ route('inventory-transactions.restock.create') }}?hub_id='+this.value">
                @forelse($headOffices as $headOffice)
                    <option value="{{ $headOffice->id }}" @selected((int) $hubId === (int) $headOffice->id)>{{ $headOffice->name }}</option>
                @empty
                    <option value="">No Head Office configured</option>
                @endforelse
            </select>
            <div class="form-text">Choose the Head Office receiving the items.</div>
        </div>
            <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="mb-0">Items Added</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addRestockItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>
        <div id="restockItemsHeader" class="row g-2 fw-semibold text-muted small">
            <div class="col-md-9">Product</div>
            <div class="col-md-2">Quantity</div>
            <div class="col-md-1">Action</div>
        </div>
        <div id="restockItems">
            <div class="row g-2 align-items-center restock-item py-2">
                <div class="col-md-9"><input type="search" class="form-control product-search" list="restockProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required>
                    <input type="hidden" name="items[0][product_id]" class="product-id"></div>
                <div class="col-md-2"><input type="number" name="items[0][quantity]" min="1" class="form-control" aria-label="Quantity" placeholder="Quantity" required></div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-restock-item" aria-label="Remove item" disabled><i class="fa-solid fa-trash"></i></button></div>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4"><label class="form-label">Reference Document No.</label><input name="reference" class="form-control" value="{{ old('reference', $restockReference) }}" readonly aria-readonly="true"></div>
            <div class="col-md-8"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="mt-4"><button class="btn btn-primary">Save Added Items</button>
            <a href="{{ route('inventory-transactions.index', ['hub_id' => $hubId]) }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

@push('scripts')
@php
    $productOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'barcode' => $product->barcode,
        'description' => $product->description ?: $product->name,
        'label' => $product->item_id.' — '.($product->name ?: $product->description).($product->description && strcasecmp(trim($product->description), trim($product->name)) !== 0 && $product->name ? ' / '.$product->description : '').($product->barcode ? ' · '.$product->barcode : '').' (stock: '.$product->stock.')',
    ])->values();
@endphp
<script>
(() => {
    const container = document.getElementById('restockItems');
    const productOptions = @json($productOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'restockProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = product.label;
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);
    setupTransactionSuggestions(container, dataList, productOptions, @json(route('hub.products.search.ajax', $hubId ?? 0)));
    let itemIndex = 1;
    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item => item.label.toLowerCase() === value);
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };
    const updateRemoveButtons = () => {
        const rows = container.querySelectorAll('.restock-item');
        rows.forEach(row => {
            row.querySelector('.remove-restock-item').disabled = rows.length <= 1;
        });
    };
    bindProductSearch(container.querySelector('.restock-item'));
    document.getElementById('addRestockItem').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-center restock-item py-2';
        row.innerHTML = `<div class="col-md-9"><input type="search" class="form-control product-search" list="restockProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required><input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" aria-label="Quantity" placeholder="Quantity" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-restock-item" aria-label="Remove item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        updateRemoveButtons();
        itemIndex++;
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('.remove-restock-item');
        if (button && !button.disabled) {
            button.closest('.restock-item').remove();
            updateRemoveButtons();
        }
    });
})();
</script>
@endpush
