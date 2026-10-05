@extends('layouts.app')

@section('content')
<style>
    .workspace-page { max-width: 1500px; }
    .allocation-header { position: relative; overflow: hidden; display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 1.35rem 1.5rem; border-radius: 20px; color: #fff; background: linear-gradient(135deg, #172554, #2563eb 62%, #38bdf8); box-shadow: 0 16px 36px rgba(37,99,235,.2); }
    .allocation-header::after { content: ''; position: absolute; width: 240px; height: 240px; right: -80px; top: -125px; border-radius: 50%; background: rgba(255,255,255,.12); pointer-events: none; }
    .workspace-hero { position: relative; z-index: 1; display: flex; align-items: center; gap: 1rem; min-width: 0; }
    .workspace-hero-icon { width: 54px; height: 54px; flex: 0 0 54px; display: inline-flex; align-items: center; justify-content: center; border-radius: 16px; color: #fff; background: linear-gradient(135deg, #1d4ed8, #38bdf8); box-shadow: 0 8px 16px rgba(37,99,235,.2); font-size: 1.25rem; }
    .workspace-hero h3 { color: #fff; }
    .workspace-hero .hero-badge { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .65rem; border: 1px solid rgba(255,255,255,.35); border-radius: 999px; background: rgba(255,255,255,.13); color: #fff; font-size: .75rem; }
    .allocation-header .header-action { position: relative; z-index: 1; flex: 0 0 auto; }
    @media (max-width: 700px) { .allocation-header { align-items: stretch; flex-direction: column; } .allocation-header .header-action { width: 100%; } .allocation-header .header-action .btn { width: 100%; } }
    .workspace-card { border: 0; border-radius: 18px; box-shadow: 0 8px 24px rgba(15,23,42,.07); }
    .allocation-stat { border: 0; border-radius: 16px; box-shadow: 0 7px 20px rgba(15,23,42,.06); }
    .allocation-stat .stat-icon { width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; border-radius: 12px; background: #eff6ff; color: #2563eb; }
    .allocation-table thead th { background: #f8fafc; color: #475569; font-size: .7rem; line-height: 1.25; letter-spacing: .04em; white-space: normal; overflow-wrap: anywhere; }
    .allocation-table tbody td { border-color: #eef2f7; }
    .allocation-table tbody tr:hover { background: #f8fbff; }
    .allocation-table { min-width: 1280px; table-layout: fixed; }
    .allocation-table .product-column { width: 28%; }
    .allocation-table .physical-column { width: 12%; }
    .allocation-table .channel-column { width: 9.5%; }
    .allocation-table .remaining-column { width: 12%; }
    .allocation-table .product-cell { width: 28%; min-width: 280px; }
    .allocation-table .physical-cell { width: 12%; text-align: center; }
    .allocation-table .channel-cell { position: relative; width: 9.5%; padding: .55rem .45rem 1.45rem; text-align: center; }
    .allocation-table .channel-input { width: 100%; min-width: 0; border-radius: 9px; text-align: center; }
    .allocation-table .channel-input:focus { border-color: #2563eb; box-shadow: 0 0 0 .18rem rgba(37,99,235,.12); }
    .allocation-table .channel-input.allocation-error { border-color: #dc3545; background: #fff5f5; box-shadow: 0 0 0 .18rem rgba(220,53,69,.12); }
    .allocation-table .sold-count { position: absolute; right: .45rem; bottom: .28rem; left: .45rem; display: flex; align-items: center; justify-content: center; min-height: 1rem; color: #64748b; font-size: .7rem; line-height: 1; white-space: nowrap; }
    .allocation-table .sold-count::before { content: ''; width: .32rem; height: .32rem; margin-right: .3rem; border-radius: 50%; background: #94a3b8; }
    .allocation-table .remaining-cell { width: 12%; background: #f0fdf4; color: #15803d; text-align: center !important; vertical-align: middle !important; }
    .allocation-table thead th:not(:first-child) { text-align: center; vertical-align: middle; }
    .allocation-table tbody td { height: 70px; vertical-align: middle; }
    .allocation-table .product-cell small { display: block; margin-top: .2rem; color: #64748b; }
</style>
@php($canEditAllocations = auth()->user()->can('manage-inventory') && $hub)
@php($returnToQueue = request('return_to') === 'verification-queue')
@php($returnHubId = request('return_hub_id') ?: $hub?->id)
<div class="p-5 workspace-page">
    <div class="allocation-header mb-4">
        <div class="workspace-hero flex-grow-1">
            <span class="workspace-hero-icon"><i class="fa-solid fa-layer-group"></i></span>
            <div>
                <div class="text-uppercase small fw-bold text-white mb-1">Inventory control center</div>
                <h3 class="fw-bold mb-1">Sales Stock Allocation</h3>
                <p class="small text-white mb-2">Distribute physical stock across sales channels and monitor what remains available.</p>
                <span class="hero-badge"><i class="fa-solid fa-chart-pie"></i> Channel allocation overview</span>
            </div>
        </div>
        @if($canEditAllocations)
            <div class="header-action">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    @if($returnToQueue && $returnHubId)
                        <a href="{{ route('hub.sales.pending', ['hubId' => $returnHubId]) }}" class="btn btn-light px-3 py-3 shadow-sm">
                            <i class="fa-solid fa-arrow-left me-2"></i>BACK TO INVENTORY VERIFICATION QUEUE
                        </a>
                    @endif
                    <button form="allocation-form" class="btn btn-primary px-4 py-3 shadow-sm">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Save Allocations
                    </button>
                </div>
            </div>
        @endif
    </div>
    @if(auth()->user()->can('manage-inventory') && ! $hub)
        <div class="alert alert-info">Select a store hub before editing allocations.</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="card allocation-stat h-100 p-3"><div class="d-flex align-items-center gap-3"><span class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></span><div><div class="small text-muted">Products shown</div><div class="fs-5 fw-bold">{{ number_format($products->total()) }}</div></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card allocation-stat h-100 p-3"><div class="d-flex align-items-center gap-3"><span class="stat-icon"><i class="fa-solid fa-store"></i></span><div><div class="small text-muted">Store scope</div><div class="fs-6 fw-bold">{{ $hub?->name ?? 'All stores' }}</div></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card allocation-stat h-100 p-3"><div class="d-flex align-items-center gap-3"><span class="stat-icon"><i class="fa-solid fa-arrows-up-down"></i></span><div><div class="small text-muted">Allocation mode</div><div class="fs-6 fw-bold">{{ $canEditAllocations ? 'Editable' : 'View only' }}</div></div></div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="card allocation-stat h-100 p-3"><div class="d-flex align-items-center gap-3"><span class="stat-icon"><i class="fa-solid {{ $hasSoldDateRange ? 'fa-calendar-check' : 'fa-circle-info' }}"></i></span><div><div class="small text-muted">{{ $hasSoldDateRange ? 'Sold-items range' : 'Page' }}</div><div class="fs-6 fw-bold">{{ $hasSoldDateRange ? $soldFrom.' to '.$soldTo : $products->currentPage().' / '.max(1, $products->lastPage()) }}</div></div></div></div></div>
    </div>

    <div class="card workspace-card p-3 mb-4">
        <form method="GET" action="{{ route('stock-allocation.index') }}" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="small text-muted fw-bold"><i class="fa-solid fa-store me-1"></i>STORE HUB</label>
                <select name="hub_id" class="form-select" onchange="this.form.submit()">
                    @if($allocationHubs->count() > 1)
                        <option value="">All stores</option>
                    @endif
                    @foreach($allocationHubs as $storeHub)
                        <option value="{{ $storeHub->id }}" @selected($hub?->id === $storeHub->id)>{{ strtoupper($storeHub->name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="small text-muted fw-bold"><i class="fa-solid fa-magnifying-glass me-1"></i>SEARCH PRODUCTS</label>
                <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Product name, item ID, or barcode">
            </div>
            <div class="col-md-2">
                <label class="small text-muted fw-bold"><i class="fa-solid fa-calendar-day me-1"></i>SOLD FROM</label>
                <input type="date" name="sold_from" value="{{ $soldFrom }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="small text-muted fw-bold"><i class="fa-solid fa-calendar-day me-1"></i>SOLD TO</label>
                <input type="date" name="sold_to" value="{{ $soldTo }}" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Apply Filters</button>
            </div>
        </form>
        <p class="small text-muted mb-0 mt-3"><i class="fa-solid fa-circle-info me-1"></i>When both sold dates are selected, this Head Office view shows only product items sold during that date range.</p>
    </div>

    <form id="allocation-form" method="POST" action="{{ route('stock-allocation.update') }}">
        @csrf
        <input type="hidden" name="hub_id" value="{{ $hub?->id ?? request('hub_id') }}">
        @if(request('product_id'))
            <input type="hidden" name="product_id" value="{{ request('product_id') }}">
        @endif
        @if(request('search'))
            <input type="hidden" name="search" value="{{ request('search') }}">
        @endif
        @if($soldFrom)
            <input type="hidden" name="sold_from" value="{{ $soldFrom }}">
        @endif
        @if($soldTo)
            <input type="hidden" name="sold_to" value="{{ $soldTo }}">
        @endif
        @if($returnToQueue)
            <input type="hidden" name="return_to" value="verification-queue">
            <input type="hidden" name="return_hub_id" value="{{ $returnHubId }}">
        @endif
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 allocation-table">
                    <colgroup>
                        <col class="product-column">
                        <col class="physical-column">
                        <col class="channel-column">
                        <col class="channel-column">
                        <col class="channel-column">
                        <col class="channel-column">
                        <col class="channel-column">
                        <col class="channel-column">
                        <col class="remaining-column">
                    </colgroup>
                    <thead class="small text-uppercase">
                        <tr>
                            <th>Product</th>
                            <th>Physical Stock Remaining</th>
                            <th>Online / Event / Restock</th>
                            <th>Wholesale</th>
                            <th>Shopee</th>
                            <th>Lazada</th>
                            <th>TikTok</th>
                            <th>Walk-In Sold</th>
                            <th class="remaining-heading">Total Allocated Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($products as $product)
                        <tr>
                            <td class="product-cell">
                                <div class="fw-semibold">{{ $product->name }}</div>
                                <small class="text-muted">{{ $product->item_id }}{{ $product->barcode ? ' · '.$product->barcode : '' }}</small>
                            </td>
                            <td class="physical-cell fw-semibold">{{ $product->stock }}</td>
                            @foreach(['online', 'wholesale', 'shopee', 'lazada', 'tiktok'] as $channel)
                                <td class="channel-cell">
                                    @if($canEditAllocations)
                                        <input type="text" inputmode="numeric" pattern="[0-9]*" min="0" max="{{ $product->starting_inventory }}" class="form-control form-control-sm channel-input"
                                            name="allocations[{{ $product->id }}][{{ $channel }}]"
                                            value="{{ $product->allocation_values[$channel] }}">
                                    @else
                                        <span>{{ in_array($channel, $assignedChannels, true) ? $product->remaining_values[$channel] : '—' }}</span>
                                    @endif
                                    @if($product->sold_values->get($channel, 0) > 0)
                                        <small class="sold-count">Sold: {{ $product->sold_values[$channel] }}</small>
                                    @endif
                                </td>
                            @endforeach
                            <td class="channel-cell">
                                <span>{{ $product->sold_values->get('walk_in', 0) }}</span>
                                @if($product->sold_values->get('walk_in', 0) > 0)
                                    <small class="sold-count">Sold via walk-in</small>
                                @endif
                            </td>
                            <td class="fw-bold remaining-cell">{{ $product->remaining_values->sum() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">No products found.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-3">{{ $products->links() }}</div>
        </div>
    </form>
</div>
<script>
    document.querySelectorAll('.channel-input').forEach((input) => {
        input.addEventListener('input', function () {
            const digitsOnly = this.value.replace(/[^\d]/g, '');
            this.value = digitsOnly === '' ? '' : String(Number.parseInt(digitsOnly, 10));
            this.classList.remove('allocation-error');
        });
    });

    document.getElementById('allocation-form')?.addEventListener('submit', function (event) {
        const form = event.currentTarget;
        const rows = form.querySelectorAll('tbody tr');
        let invalidInput = null;
        let invalidMessage = '';

        form.querySelectorAll('.allocation-error').forEach((input) => input.classList.remove('allocation-error'));

        rows.forEach((row) => {
            if (invalidInput) return;

            const inputs = [...row.querySelectorAll('.channel-input')];
            if (!inputs.length) return;

            const physicalStock = Number(row.querySelector('.physical-cell')?.textContent.trim() || 0);
            const startingInventory = inputs.reduce((maximum, input) => Math.max(maximum, Number(input.max || 0)), physicalStock);
            let allocated = 0;
            let exceedingInput = null;
            for (const input of inputs) {
                allocated += Math.max(0, Number.parseInt(input.value || '0', 10) || 0);
                if (allocated > startingInventory) {
                    exceedingInput = input;
                    break;
                }
            }

            if (exceedingInput) {
                invalidInput = exceedingInput;
                invalidInput.classList.add('allocation-error');
                const productName = row.querySelector('.product-cell .fw-semibold')?.textContent.trim() || 'this product';
                invalidMessage = `${productName} exceeds its available stock of ${startingInventory} units. Review the highlighted quantity.`;
            }
        });

        if (!invalidInput) return;

        event.preventDefault();
        invalidInput.focus({ preventScroll: true });
        invalidInput.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });

        if (window.AppAlert?.show) {
            AppAlert.show(invalidMessage, 'error', {
                title: 'Allocation exceeds available stock',
                buttonLabel: 'Review quantity',
            });
        } else {
            window.alert(invalidMessage);
        }
    });
</script>
@endsection
