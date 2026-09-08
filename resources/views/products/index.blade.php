@extends('layouts.app')

@section('content')

<div class="p-5">
    <div class="mb-4">
        <h3 class="fw-bold text-dark mb-1">Master Product Stock Sheets</h3>
        <p class="text-muted small mb-0">Review inventory balances per physical store hub.</p>
    </div>

    {{-- Filter Card (Store Hub + Search & Filters) --}}
    <div class="card border-0 shadow-sm rounded-4 p-3 mb-4 bg-white">
        <div class="row align-items-end g-3">
            {{--Selection Store Hub--}}
            <div class="col-md-3">
                <label class="small text-muted fw-bold mb-1">SELECT STORE HUB</label>
                <style>
                    .custom-hub-select {
                        font-size: 16px !important;
                        font-weight: 600 !important;
                    }
                    .custom-hub-select option {
                        font-size: 16px !important;
                    }
                    /* Force fix for stuck Bootstrap modal backdrops and click lockouts */
                    .modal-backdrop {
                        display: none !important;
                    }
                    body.modal-open {
                        overflow: auto !important;
                        padding-right: 0 !important;
                    }
                    .modal {
                        background: rgba(0, 0, 0, 0.5);
                    }
                </style>
                <select name="hub_id" id="storeHubSelect" class="form-select border-0 bg-light py-2 custom-hub-select" onchange="window.location.href='{{ route('products.index') }}?hub_id=' + this.value + '&field={{ request('field') }}&search={{ request('search') }}'">
                    <option value="">-- CHOOSE A STORE --</option>
                    @foreach($hubs as $hub)
                        <option value="{{ $hub->id }}" {{ request('hub_id') == $hub->id ? 'selected' : '' }}>
                            {{ strtoupper($hub->name) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Search and Filter Form --}}
            <div class="col-md-9">
                <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-end">
                    @if(request('hub_id'))
                        <input type="hidden" name="hub_id" value="{{ request('hub_id') }}">
                    @endif

                    <div class="col-md-3">
                        <label class="small text-muted fw-bold mb-1">FILTER BY</label>
                        <select name="field" class="form-select border-0 bg-light py-2">
                            <option value="name" {{ request('field') == 'name' ? 'selected' : '' }}>Product Name</option>
                            <option value="item_id" {{ request('field') == 'item_id' ? 'selected' : '' }}>Item ID</option>
                            <option value="barcode" {{ request('field') == 'barcode' ? 'selected' : '' }}>Barcode</option>
                            <option value="brand" {{ request('field') == 'brand' ? 'selected' : '' }}>Brand</option>
                            <option value="retail_group" {{ request('field') == 'retail_group' ? 'selected' : '' }}>Retail Group</option>
                            <option value="retail_department" {{ request('field') == 'retail_department' ? 'selected' : '' }}>Retail Dept</option>
                            <option value="unit_type" {{ request('field') == 'unit_type' ? 'selected' : '' }}>Unit Type</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="small text-muted fw-bold mb-1">SEARCH KEYWORD</label>
                        <input type="text" name="search" class="form-control border-0 bg-light py-2" placeholder="Search products..." value="{{ request('search') }}">
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2">
                            <i class="fa-solid fa-search me-1"></i> Search
                        </button>
                        <a href="{{ route('products.index', ['hub_id' => request('hub_id')]) }}" class="btn btn-outline-secondary py-2" title="Reset Search">
                            <i class="fa-solid fa-rotate-right"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4">{{ session('success') }}</div>
    @endif

    {{-- Product Table Wrapped in Bulk Delete Form --}}
    <form action="{{ route('products.bulk-destroy') }}" method="POST" id="bulkDeleteForm">
        @csrf

        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">
                    <i class="fa-solid fa-warehouse me-2 text-muted"></i> 
                    {{ isset($selectedHub) && $selectedHub ? strtoupper($selectedHub->name) : 'ALL STORES' }}
                </h5>
                
                @can('full-access')
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm shadow-sm fw-semibold px-3 py-2" id="unifiedActionBtn" onclick="handleUnifiedAction()">
                        <i class="fa-solid fa-square-check me-2" id="unifiedBtnIcon"></i> <span id="unifiedBtnLabel">Select Rows Mode</span>
                    </button>
                </div>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted fw-bold" style="font-size: 11px;">
                            <th style="width: 20%;">Product Name</th>
                            <th style="width: 10%;">Item ID</th>
                            <th style="width: 13%;">Barcode</th>
                            <th style="width: 11%;">Brand</th>    
                            <th style="width: 11%;">Retail Group</th>    
                            <th style="width: 10%;">Retail Dept</th>
                            <th style="width: 8%;">Unit Type</th>      
                            <th class="text-end" style="width: 9%;">Retail Price</th>
                            <th class="text-center" style="width: 8%;">Branch Stock</th>
                            @can('manage-inventory')
                            <th class="text-end action-header-text" style="width: 9%;">Actions</th>
                            @can('full-access')
                            <th class="text-center select-col-header" style="display: none; width: 50px;">
                               <input type="checkbox" id="selectAllCheckbox" class="form-check-input" title="Select All">
                            </th>
                            @endcan
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            <tr>
                                <td>{{ $product->name }}</td>
                                <td>{{ $product->item_id }}</td>
                                <td>{{ $product->barcode ?? '-' }}</td>
                                <td>
                                    @php
                                        $brandData = json_decode($product->brand);
                                        $displayBrand = is_object($brandData) ? ($brandData->brand_name ?? $brandData->name ?? $product->brand) : $product->brand;
                                    @endphp
                                    {{ $displayBrand }}
                                </td>
                                <td>{{ $product->retail_group }}</td>    
                                <td>{{ $product->retail_department }}</td>   
                                <td>{{ $product->unit_type ?? '-' }}</td>
                                <td class="text-end">₱{{ number_format($product->sales_price, 2) }}</td>
                                <td class="text-center">{{ $product->stock ?? 0 }}</td>
                                
                                @can('manage-inventory')
                                <td class="text-end standard-actions-col">
                                    <div class="d-flex justify-content-end gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProductModal{{ $product->id }}">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm {{ $product->status == 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}" title="Toggle Status" onclick="document.getElementById('toggle-form-{{ $product->id }}').submit();">
                                            <i class="fa-solid {{ $product->status == 'active' ? 'fa-toggle-on' : 'fa-toggle-off' }}"></i>
                                        </button>
                                        @can('full-access')
                                        <button type="button" class="btn btn-sm btn-danger shadow-sm" title="Delete Product" onclick="confirmSingleDelete({{ $product->id }})">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                        @endcan
                                    </div>
                                </td>

                                @can('full-access')
                                <td class="text-center select-col" style="display: none;">
                                   <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" class="form-check-input product-checkbox" onchange="updateUnifiedButtonState()">
                                </td>
                                @endcan
                                @endcan
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-5 text-muted">No products found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination Links Container --}}
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                <div class="text-muted small">
                    Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} entries
                </div>
                <div>
                    {{ $products->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </form>
</div>

{{-- ISOLATED NON-NESTED FORMS FOR ROW ACTIONS --}}
@foreach($products as $product)
    <form id="toggle-form-{{ $product->id }}" action="{{ route('products.toggle', $product->id) }}" method="POST" class="d-none">
        @csrf @method('PATCH')
    </form>

    <form id="delete-form-{{ $product->id }}" action="{{ route('products.destroy', $product->id) }}" method="POST" class="d-none">
        @csrf @method('DELETE')
    </form>
@endforeach

{{-- EDIT PRODUCT MODALS (MOVED OUTSIDE OF ANY TABLES OR FORMS TO PREVENT INTERACTION LOCKS) --}}
@foreach($products as $product)
<div class="modal fade" id="editProductModal{{ $product->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('products.update', $product->id) }}" method="POST" class="modal-content border-0 shadow-lg">
            @csrf @method('PUT')
            <div class="modal-header bg-white border-bottom p-4">
                <h5 class="modal-title fw-bold">Edit Product: {{ $product->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white" style="max-height: 70vh; overflow-y: auto;">
                <div class="row g-4">
                    <div class="col-12">
                        
                        {{-- General Information --}}
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-circle-info me-2"></i>General Information</h6>
                                <div class="row g-3">
                                    <!-- Row 1: Item ID & Product Name -->
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-muted">Item ID</label>
                                        <input type="text" name="item_id" class="form-control" value="{{ $product->item_id }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-muted">Product Name</label>
                                        <input type="text" name="name" class="form-control" value="{{ $product->name }}">
                                    </div>

                                    <!-- Description (Full Width) -->
                                    <div class="col-12">
                                        <label class="small fw-bold text-muted">Description</label>
                                        <textarea name="description" class="form-control" rows="2">{{ $product->description }}</textarea>
                                    </div>

                                    <!-- Row 2: Barcode & Brand Name -->
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-muted">Barcode</label>
                                        <input type="text" name="barcode" class="form-control" value="{{ $product->barcode }}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="small fw-bold text-muted">Brand Name</label>
                                        <select name="brand" class="form-select">
                                            <option value="">-- Select Brand --</option>
                                            @foreach($brands as $brand)
                                                @php $brandName = $brand->brand_name ?? $brand->name; @endphp
                                                <option value="{{ $brandName }}" {{ strcasecmp(trim($product->brand ?? ''), trim($brandName)) == 0 ? 'selected' : '' }}>
                                                    {{ $brandName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <!-- Row 3: Retail Group & Retail Dept & Unit Type -->
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Retail Group</label>
                                        <select name="retail_group" class="form-select">
                                            <option value="">-- Select Group --</option>
                                            @foreach($groups as $group)
                                                <option value="{{ $group->name }}" {{ strcasecmp(trim($product->retail_group ?? ''), trim($group->name)) == 0 ? 'selected' : '' }}>
                                                    {{ $group->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Retail Dept</label>
                                        <select name="retail_department" class="form-select">
                                            <option value="">-- Select Department --</option>
                                            @foreach($departments as $dept)
                                                <option value="{{ $dept->name }}" {{ strcasecmp(trim($product->retail_department ?? ''), trim($dept->name)) == 0 ? 'selected' : '' }}>
                                                    {{ $dept->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Unit Type</label>
                                        <select name="unit_type" class="form-select">
                                            <option value="">-- Select Unit Type --</option>
                                            @foreach($unitTypes as $unitType)
                                                @php $code = $unitType->abbreviation; @endphp
                                                <option value="{{ $code }}" {{ (isset($product) && strcasecmp(trim($product->unit_type), trim($code)) == 0) ? 'selected' : '' }}>
                                                    {{ $code }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pricing Details -->
                        <div class="card border-0 shadow-sm mt-3">
                            <div class="card-body">
                                <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-tag me-2"></i>Pricing Details</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Cost Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Sales Price</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="sales_price" value="{{ old('sales_price', $product->sales_price) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Wholesale</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="wholesale_price" value="{{ old('wholesale_price', $product->wholesale_price) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Shopee</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="shopee_price" value="{{ old('shopee_price', $product->shopee_price) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Lazada</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="lazada_price" value="{{ old('lazada_price', $product->lazada_price) }}" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">TikTok</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light text-muted">₱</span>
                                            <input type="text" name="tiktok_price" value="{{ old('tiktok_price', $product->tiktok_price) }}" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer p-4">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Changes</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let isSelectionMode = false;

    function confirmSingleDelete(productId) {
        Swal.fire({
            title: 'Confirm Deletion',
            text: 'Are you sure? This action cannot be undone.',
            icon: 'none',
            showCancelButton: true,
            buttonsStyling: false,
            customClass: {
                popup: 'rounded-4 shadow-sm border p-4',
                title: 'fs-5 fw-bold text-dark',
                htmlContainer: 'text-muted small mb-4',
                actions: 'gap-2 w-100 justify-content-center m-0',
                confirmButton: 'btn btn-danger px-4 py-2 rounded-2 fw-semibold',
                cancelButton: 'btn btn-secondary px-4 py-2 rounded-2 fw-semibold'
            },
            confirmButtonText: 'Yes, Delete It',
            cancelButtonText: 'No, Keep It',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('delete-form-' + productId).submit();
            }
        });
    }

    function handleUnifiedAction() {
        const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;

        if (isSelectionMode && checkedCount > 0) {
            Swal.fire({
                title: 'Confirm Deletion',
                text: 'Are you sure you want to delete ' + checkedCount + ' selected item(s)? This cannot be undone.',
                icon: 'none',
                showCancelButton: true,
                buttonsStyling: false,
                customClass: {
                    popup: 'rounded-4 shadow-sm border p-4',
                    title: 'fs-5 fw-bold text-dark',
                    htmlContainer: 'text-muted small mb-4',
                    actions: 'gap-2 w-100 justify-content-center m-0',
                    confirmButton: 'btn btn-danger px-4 py-2 rounded-2 fw-semibold',
                    cancelButton: 'btn btn-secondary px-4 py-2 rounded-2 fw-semibold'
                },
                confirmButtonText: 'Yes, Delete It',
                cancelButtonText: 'No, Keep It',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('bulkDeleteForm').submit();
                }
            });
            return;
        }

        isSelectionMode = !isSelectionMode;
        
        const standardCols = document.querySelectorAll('.standard-actions-col');
        const selectCols = document.querySelectorAll('.select-col');
        const actionHeaderText = document.querySelector('.action-header-text');
        const selectColHeader = document.querySelector('.select-col-header');
        const btn = document.getElementById('unifiedActionBtn');
        const btnIcon = document.getElementById('unifiedBtnIcon');
        const btnLabel = document.getElementById('unifiedBtnLabel');

        standardCols.forEach(col => col.style.display = isSelectionMode ? 'none' : '');
        selectCols.forEach(col => col.style.display = isSelectionMode ? '' : 'none');

        if (isSelectionMode) {
            if (actionHeaderText) actionHeaderText.style.display = 'none';
            if (selectColHeader) selectColHeader.style.display = '';
            btn.className = 'btn btn-outline-secondary btn-sm shadow-sm fw-semibold px-3 py-2';
            btnIcon.className = 'fa-solid fa-square-xmark me-2';
            btnLabel.textContent = 'Exit Selection Mode';
        } else {
            if (actionHeaderText) actionHeaderText.style.display = '';
            if (selectColHeader) selectColHeader.style.display = 'none';
            btn.className = 'btn btn-outline-secondary btn-sm shadow-sm fw-semibold px-3 py-2';
            btnIcon.className = 'fa-solid fa-square-check me-2';
            btnLabel.textContent = 'Select Rows Mode';
            
            document.querySelectorAll('.product-checkbox, #selectAllCheckbox').forEach(cb => cb.checked = false);
        }
    }

    function updateUnifiedButtonState() {
        if (!isSelectionMode) return;

        const checkedCount = document.querySelectorAll('.product-checkbox:checked').length;
        const btn = document.getElementById('unifiedActionBtn');
        const btnIcon = document.getElementById('unifiedBtnIcon');
        const btnLabel = document.getElementById('unifiedBtnLabel');

        if (checkedCount > 0) {
            btn.className = 'btn btn-danger btn-sm shadow-sm fw-semibold px-3 py-2';
            btnIcon.className = 'fa-solid fa-trash-can me-2';
            btnLabel.textContent = 'Delete Selected Items (' + checkedCount + ')';
        } else {
            btn.className = 'btn btn-outline-secondary btn-sm shadow-sm fw-semibold px-3 py-2';
            btnIcon.className = 'fa-solid fa-square-xmark me-2';
            btnLabel.textContent = 'Exit Selection Mode';
        }
    }

    document.getElementById('selectAllCheckbox')?.addEventListener('change', function() {
        let checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(cb => cb.checked = this.checked);
        updateUnifiedButtonState();
    });

    document.addEventListener('hidden.bs.modal', function () {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
</script>

@endsection