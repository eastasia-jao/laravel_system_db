@extends('layouts.app')
@section('content')
<div class="container-fluid py-3">
<style>
.catalog-controls { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; margin-bottom: 16px; align-items: stretch; }
@media (max-width: 1100px) { .catalog-controls { grid-template-columns: minmax(0, 1fr); } }
.catalog-toolbar { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; }
.catalog-search { width: 100%; }
.catalog-destination { flex: 1 1 220px; width: auto; min-width: 0; }
</style>
    <x-page-header class="mb-4" eyebrow="Inventory workspace" title="Shared Product Catalog" description="Manage the Head Office shared catalog and add products to non-Head Office branches. Each branch keeps its own stock and prices." icon="fa-book-open">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('products.index', ['hub_id' => $sourceHub->id]) }}"><i class="fa-solid fa-arrow-left me-1"></i>Head Office Inventory</a></x-slot:actions>
    </x-page-header>
    <form id="catalogAssignment" method="POST" action="{{ route('catalog.assign') }}">@csrf<input type="hidden" name="source_hub_id" value="{{ $sourceHub->id }}"></form>
    <div class="catalog-controls">
    <div class="catalog-toolbar"><label class="form-label fw-semibold" for="catalogSearch">Find a product</label><form method="GET" class="input-group catalog-search"><input type="hidden" name="hub_id" value="{{ $sourceHub->id }}"><input id="catalogSearch" type="search" class="form-control" name="search" value="{{ request('search') }}" placeholder="Name, Item ID, barcode or brand" aria-label="Search catalog"><button class="btn btn-primary">Search</button><a class="btn btn-outline-secondary" href="{{ route('catalog.index', ['hub_id' => $sourceHub->id]) }}">Clear</a></form><div class="small text-muted mt-2">Combine keywords, for example “paint blue 500”.</div></div>

        <div class="catalog-toolbar"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" form="catalogAssignment" name="all_products" value="1" id="assignWholeCatalog" onchange="document.querySelectorAll('.catalog-choice').forEach(c => c.disabled = this.checked)"><label class="form-check-label" for="assignWholeCatalog">Add all catalog products</label></div><p class="small text-muted mb-3">The full catalog is added immediately to the selected non-Head Office branch. Keep this page open until the request finishes; no inventory worker is required.</p>
        <label for="destinationBranch" class="form-label fw-semibold">Destination branch</label><div class="d-flex flex-wrap gap-2 mb-3"><select id="destinationBranch" class="form-select catalog-destination" form="catalogAssignment" name="hub_id" required aria-label="Destination branch"><option value="">Choose a branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int) old('hub_id') === (int) $branch->id)>{{ $branch->name }}</option>@endforeach</select>
        <button form="catalogAssignment" class="btn btn-success text-nowrap">Add selected to branch</button></div>
        <p class="small text-muted">New branch records start with zero stock and no prices. Stock receipts and price setup remain separate.</p></div>
    </div>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th><input type="checkbox" aria-label="Select this page" onchange="document.querySelectorAll('.catalog-choice').forEach(c => c.checked = this.checked)"></th><th>Item ID</th><th>Product</th><th>Barcode</th><th>Brand</th><th>Branch records</th></tr></thead><tbody>
            @forelse($catalog as $item)<tr><td><input class="catalog-choice" type="checkbox" form="catalogAssignment" name="catalog_ids[]" value="{{ $item->id }}" aria-label="Select {{ $item->name }}"></td><td>{{ $item->item_id }}</td><td>{{ $item->name }}</td><td>{{ $item->barcode }}</td><td>{{ $item->brand }}</td><td>{{ $item->branch_inventories_count }}</td></tr>@empty<tr><td colspan="6">No matching products.</td></tr>@endforelse
        </tbody></table></div>

    {{ $catalog->links() }}
</div>
@endsection
