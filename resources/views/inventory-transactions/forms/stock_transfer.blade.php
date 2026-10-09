@extends('layouts.app')

@section('content')
@php
    $branchTransfer = $type === 'branch_transfer';
    $transferDirection = $transferDirection ?? 'ho_to_branch';
    $transferDirectionLabel = $transferDirection === 'branch_to_ho' ? 'BRANCH to HO' : 'HO to BRANCH';
    $transferRoute = $branchTransfer ? 'inventory-transactions.branch-transfer.create' : 'inventory-transactions.transfer.create';
    $directionQuery = $branchTransfer ? '' : '&direction='.$transferDirection;
    $lockSourceStore = $branchTransfer
        && auth()->user()?->role === 'sales_associate'
        && $transferSourceHubs->count() === 1;
@endphp
<style>
    .transaction-page-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem 1.5rem;
        border: 1px solid #dbeafe;
        border-radius: 1rem;
        background: linear-gradient(110deg, #eff6ff 0%, #fff 72%);
    }
    .transaction-page-heading-icon {
        display: grid;
        flex: 0 0 3rem;
        width: 3rem;
        height: 3rem;
        place-items: center;
        border-radius: .875rem;
        background: #dbeafe;
        color: #2563eb;
        font-size: 1.25rem;
    }
    .transaction-page-heading h3 { font-size: clamp(1.15rem, 2vw, 1.5rem); }
    .transaction-form-card { border: 1px solid #e2e8f0 !important; }
    @media (max-width: 575.98px) {
        .transaction-page-heading { align-items: flex-start; padding: 1rem; }
        .transaction-page-heading-icon { flex-basis: 2.5rem; width: 2.5rem; height: 2.5rem; }
    }
    #transferItems {
        max-height: 430px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.5rem;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    .transfer-items-header,
    .transfer-item {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 150px 42px;
        gap: 0.5rem;
        align-items: center;
    }
    .transfer-items-header {
        padding: 0 0.25rem 0.35rem;
        color: #475569;
        font-size: 0.875rem;
        font-weight: 600;
    }
    .transfer-item {
        margin-bottom: 0.5rem;
    }
    .transfer-item:last-child {
        margin-bottom: 0;
    }
    @media (max-width: 575.98px) {
        .transfer-items-header,
        .transfer-item {
            grid-template-columns: minmax(0, 1fr) 90px 38px;
            gap: 0.35rem;
        }
    }
</style>
<div class="workspace-page inventory-entry-page">
    <x-page-header class="mb-3" eyebrow="Inventory movement" :title="'Stock Transfer'.($branchTransfer ? ' (BRANCH to BRANCH)' : ' ('.$transferDirectionLabel.')')" :description="$branchTransfer ? 'Submit a branch-to-branch transfer for inventory staff or admin review. Stock changes only after approval.' : 'Transfer products between Head Office and a branch in one transaction.'" icon="fa-truck-ramp-box" />
    
    <form method="POST" action="{{ route($branchTransfer ? 'inventory-transactions.branch-transfer.store' : 'inventory-transactions.store') }}" class="card transaction-form-card shadow-sm rounded-4 p-4">
        @csrf
        @include('inventory-transactions.forms.bulk-items')
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="row g-3">
            @unless($branchTransfer)
                <div class="col-md-3">
                    <label class="form-label">Transfer Type</label>
                    <select name="transfer_direction" id="transferDirection" class="form-select" required onchange="window.location.href='{{ route($transferRoute) }}?direction='+this.value+'&hub_id='+document.getElementById('sourceHub').value">
                        <option value="ho_to_branch" @selected($transferDirection === 'ho_to_branch')>Head Office to Branch</option>
                        <option value="branch_to_ho" @selected($transferDirection === 'branch_to_ho')>Branch to Head Office</option>
                    </select>
                </div>
            @endunless
            <div class="{{ $branchTransfer ? 'col-md-4' : 'col-md-3' }}">
                <label class="form-label">{{ $branchTransfer ? 'Source Store' : ($transferDirection === 'branch_to_ho' ? 'Source Branch' : 'Source Head Office') }}</label>
                @if($lockSourceStore)
                    <input type="hidden" name="store_hub_id" value="{{ $transferSourceHubs->first()->id }}">
                    <input type="text" class="form-control bg-light" value="{{ $transferSourceHubs->first()->name }}" readonly aria-readonly="true">
                @else
                    <select name="store_hub_id" id="sourceHub" class="form-select" required onchange="window.location.href='{{ route($transferRoute) }}?hub_id='+this.value+'{{ $directionQuery }}'">
                        @if($transferSourceHubs->isEmpty())
                            <option value="">{{ $branchTransfer || $transferDirection === 'branch_to_ho' ? 'No source branches available' : 'Enable Head Office Mode for an active store first' }}</option>
                        @endif
                        @foreach($transferSourceHubs as $hub)
                            <option value="{{ $hub->id }}" @selected((int) $hubId === $hub->id)>{{ $hub->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="{{ $branchTransfer ? 'col-md-4' : 'col-md-3' }}">
                <label class="form-label">{{ $branchTransfer ? 'Target Store' : ($transferDirection === 'branch_to_ho' ? 'Target Head Office' : 'Target Branch') }}</label>
                <select name="target_hub_id" class="form-select" required>
                    <option value="">Select target {{ $branchTransfer ? 'store' : ($transferDirection === 'branch_to_ho' ? 'Head Office' : 'branch') }}</option>
                    @foreach($allHubs as $hub)
                        @if((int) $hubId !== $hub->id)
                            <option value="{{ $hub->id }}">{{ $hub->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div class="{{ $branchTransfer ? 'col-md-4' : 'col-md-3' }}">
                <label class="form-label">Transfer Date</label>
                <input type="date" name="occurred_on" value="{{ old('occurred_on', now()->toDateString()) }}" class="form-control" required>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-4 mb-2">
            <h5 class="fw-bold mb-0">Items to Transfer</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" id="addTransferItem">
                <i class="fa-solid fa-plus me-1"></i>Add Item
            </button>
        </div>
        <div id="transferItems">
            <div class="transfer-items-header" aria-hidden="true">
                <span>Product</span>
                <span>Quantity</span>
                <span></span>
            </div>
            <div class="transfer-item">
                <div>
                    <input type="search" class="form-control product-search" list="transferProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required>
                    <input type="hidden" name="items[0][product_id]" class="product-id">
                </div>
                <div>
                    <input type="number" name="items[0][quantity]" min="1" class="form-control" aria-label="Quantity" required>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-danger remove-transfer-item" aria-label="Remove item" disabled><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <div class="col-md-4">
                <label class="form-label">Transfer Document No.</label>
                <input name="reference" class="form-control" value="{{ old('reference', $transferReference) }}" placeholder="Transfer document no." readonly aria-readonly="true">
            </div>
            <div class="col-md-8"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
        </div>
        <div class="mt-4">
            @if(! $branchTransfer || auth()->user()?->can('submit-branch-transfers') || auth()->user()?->can('manage-inventory'))
                <button class="btn btn-primary">{{ $branchTransfer ? (auth()->user()?->can('manage-inventory') ? 'Transfer Now' : 'Submit for Approval') : 'Save Transfer' }}</button>
            @endif
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
            'label' => $product->item_id.' — '.($product->name ?: $product->description).($product->description && strcasecmp(trim($product->description), trim($product->name)) !== 0 && $product->name ? ' / '.$product->description : '').($product->barcode ? ' · '.$product->barcode : '').' (stock: '.$product->stock.')',
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
        option.value = product.label;
        dataList.appendChild(option);
    });
    document.body.appendChild(dataList);
    setupTransactionSuggestions(container, dataList, productOptions, @json(route('hub.products.search.ajax', $hubId ?? 0)));

    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item =>
                item.label.toLowerCase() === value
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
        });
    };

    bindProductSearch(container.querySelector('.transfer-item'));

    const updateRemoveButtons = () => {
        const rows = container.querySelectorAll('.transfer-item');
        rows.forEach(row => {
            row.querySelector('.remove-transfer-item').disabled = rows.length <= 1;
        });
    };
    updateRemoveButtons();

    addButton.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'transfer-item';
        row.innerHTML = `
            <div><input type="search" class="form-control product-search" list="transferProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" aria-label="Product" required>
                <input type="hidden" name="items[${itemIndex}][product_id]" class="product-id"></div>
            <div><input type="number" name="items[${itemIndex}][quantity]" min="1" class="form-control" aria-label="Quantity" required></div>
            <div><button type="button" class="btn btn-outline-danger remove-transfer-item" aria-label="Remove item"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></div>`;
        container.appendChild(row);
        bindProductSearch(row);
        itemIndex++;
        updateRemoveButtons();
    });

    container.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-transfer-item');
        if (button && !button.disabled) {
            button.closest('.transfer-item').remove();
            updateRemoveButtons();
        }
    });
})();
</script>
@endpush
