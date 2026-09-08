@extends('layouts.app')

@section('content')
<style>
    #restockItems { max-height: 430px; overflow-y: auto; overflow-x: hidden; padding: .25rem .5rem .25rem 0; border: 1px solid #dee2e6; border-radius: .375rem; }
</style>
<div class="p-5">
    <h3 class="fw-bold mb-1">Restock / Added Items</h3>
    <p class="text-muted small mb-4">Record items received by Head Office from a warehouse request or a branch stock transfer.</p>
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif
    <form method="POST" action="{{ route('inventory-transactions.store') }}" class="card border-0 shadow-sm rounded-4 p-4">
        @csrf
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
            <div class="col-md-4"><label class="form-label">Added From</label><select name="restock_source" id="restockSource" class="form-select" required>
                <option value="warehouse_request">Restock / Added from Request (Warehouse HO)</option>
                <option value="stock_transfer">Added from Stock Transfer (Store Branch &gt; Head Office)</option>
            </select></div>
            <div class="col-md-6 d-none" id="sourceBranchField"><label class="form-label">Source Store Hub</label><select name="source_hub_id" id="sourceHub" class="form-select">
                <option value="">Select source branch</option>
                @foreach($allHubs as $hub)
                    @if(!$hub->is_head_office)<option value="{{ $hub->id }}">{{ $hub->name }}</option>@endif
                @endforeach
            </select></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="mb-0">Items Added</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addRestockItem"><i class="fa-solid fa-plus me-1"></i>Add Item</button>
        </div>
        <div id="restockItems">
            <div class="row g-2 align-items-end restock-item mb-2">
                <div class="col-md-9"><label class="form-label">Product</label><input type="search" class="form-control product-search" list="restockProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                    <input type="hidden" name="items[0][product_id]" class="product-id"></div>
                <div class="col-md-2"><label class="form-label">Quantity</label><input type="number" name="items[0][quantity]" min="1" class="form-control" required></div>
                <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-restock-item" disabled><i class="fa-solid fa-trash"></i></button></div>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4"><label class="form-label">Reference</label><input name="reference" class="form-control" placeholder="Request / transfer no."></div>
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
        'label' => $product->item_id.' — '.$product->name.($product->barcode ? ' · '.$product->barcode : '').' (stock: '.$product->stock.')',
    ])->values();
@endphp
<script>
(() => {
    const container = document.getElementById('restockItems');
    const source = document.getElementById('restockSource');
    const sourceField = document.getElementById('sourceBranchField');
    const sourceHub = document.getElementById('sourceHub');
    const productOptions = @json($productOptions);
    const dataList = document.createElement('datalist');
    dataList.id = 'restockProductOptions';
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
            const product = productOptions.find(item => `${item.label} | ${item.barcode || ''} | ${item.description}`.toLowerCase() === value);
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };
    const updateSource = () => {
        const transfer = source.value === 'stock_transfer';
        sourceField.classList.toggle('d-none', !transfer);
        sourceHub.required = transfer;
        if (!transfer) sourceHub.value = '';
    };
    bindProductSearch(container.querySelector('.restock-item'));
    source.addEventListener('change', updateSource);
    updateSource();
    document.getElementById('addRestockItem').addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'row g-2 align-items-end restock-item mb-2';
        row.innerHTML = `<div class="col-md-9"><input type="search" class="form-control product-search" list="restockProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required><input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div class="col-md-2"><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" required></div>
            <div class="col-md-1"><button type="button" class="btn btn-outline-danger remove-restock-item"><i class="fa-solid fa-trash"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        itemIndex++;
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('.remove-restock-item');
        if (button && !button.disabled) button.closest('.restock-item').remove();
    });
})();
</script>
@endpush
