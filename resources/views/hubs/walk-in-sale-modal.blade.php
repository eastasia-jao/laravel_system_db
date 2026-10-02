<!-- Walk-In Sale Modal -->
<style>
    #walkInSaleModal .modal-dialog { max-width: 900px; }
    #walkInSaleModal .modal-header,
    #walkInSaleModal .modal-footer { padding: .75rem 1rem; }
    #walkInSaleModal .modal-body { padding: 1rem; }
    #walkInSaleModal .modal-title { font-size: 1rem; }
    #walkInSaleModal .form-label { margin-bottom: .25rem; font-size: .8rem; }
    #walkInSaleModal .form-control,
    #walkInSaleModal .form-select { font-size: .85rem; min-height: 34px; }
    #walkInSaleModal .form-text { font-size: .75rem; }
    #walkInSaleModal h6 { font-size: .85rem; }
    #walk-in-product-rows {
        max-height: 190px;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-gutter: stable;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        padding: .65rem;
    }
    #walk-in-product-rows .product-row { margin-bottom: .65rem !important; }
    #walk-in-product-rows .product-row:last-child { margin-bottom: 0 !important; }
    #walkInSaleModal .product-search { min-height: 34px; }
    @media (max-width: 767.98px) {
        #walk-in-product-rows { max-height: 240px; }
        #walkInSaleModal .modal-footer { gap: .5rem; }
    }
</style>
<div class="modal fade" id="walkInSaleModal" tabindex="-1" aria-labelledby="walkInSaleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form action="{{ route('sales.storeMultiChannelSale') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column overflow-hidden">
                @csrf
                <input type="hidden" name="store_hub_id" id="walkInStoreHubId" value="{{ $hub->id }}">
                <input type="hidden" name="sales_channel" value="walk_in">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="walkInSaleModalLabel"><i class="fa-solid fa-cash-register me-2"></i> Record Walk-In Sale</h5>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Attach the order slip for review. Stock is deducted after inventory verification.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6"><label class="form-label small">Sale Date</label><input type="date" name="order_date" class="form-control" value="{{ now()->toDateString() }}" required></div>
                        <div class="col-md-6"><label class="form-label small">Order Number</label><div class="form-control bg-light text-muted">Automatically generated (e.g. WALK-IN001)</div></div>
                    </div>
                    
                    <!-- Customer Information Section -->
                    <h6 class="text-secondary fw-bold mb-2"><i class="fa-solid fa-user me-1"></i> Customer Information</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" id="walkInCustomerName" class="form-control customer-name-input" list="existingWalkInCustomers" required placeholder="Enter or search customer name" autocomplete="name">
                            <datalist id="existingWalkInCustomers"></datalist>
                            <div class="form-text">Choose an existing Walk-In customer or enter a new customer name.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Contact Number</label>
                            <input type="tel" name="contact_number" id="walkInCustomerContact" class="form-control philippine-contact-number" placeholder="+639123456789" inputmode="tel" maxlength="13" pattern="\+639[0-9]{9}" title="Use +63 followed by 10 mobile digits, for example +639123456789">
                        </div>
                        <script>
                            document.querySelectorAll('.philippine-contact-number').forEach((input) => {
                                input.addEventListener('input', function () {
                                    let value = this.value.replace(/[^\d+]/g, '');
                                    if (value.startsWith('09')) value = '+63' + value.slice(1);
                                    if (value.startsWith('639')) value = '+' + value;
                                    this.value = value.slice(0, 13);
                                });
                            });
                        </script>
                        <div class="col-md-3">
                            <label class="form-label small">Address</label>
                            <input type="text" name="address" id="walkInCustomerAddress" class="form-control" placeholder="Customer address" autocomplete="street-address">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Branch / Location</label>
                            <select name="location" id="walkInLocation" class="form-select" required @disabled($walkInHubs->count() <= 1)>
                                @foreach($walkInHubs as $walkInHub)
                                    <option value="{{ $walkInHub->name }}"
                                        data-hub-id="{{ $walkInHub->id }}"
                                        data-products-url="{{ route('hub.products.search.ajax', $walkInHub->id) }}"
                                        data-customers-url="{{ route('hub.sales.customers', $walkInHub->id) }}"
                                        @selected((int) $walkInHub->id === (int) $hub->id)>
                                        {{ $walkInHub->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($walkInHubs->count() === 1)
                                <input type="hidden" name="location" value="{{ $walkInHubs->first()->name }}">
                            @endif
                        </div>
                    </div>

                    <hr class="my-2">

                    <!-- Payment Details Section -->
                    <h6 class="text-secondary fw-bold mb-2"><i class="fa-solid fa-wallet me-1"></i> Payment Information</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small">Payment Method <span class="text-danger">*</span></label>
                            <select name="mode_of_payment" id="walkInPaymentMethod" class="form-select" required onchange="handlePaymentMethodChange(this)">
                                <option value="CASH">Cash</option>
                                <option value="GCASH">GCash</option>
                                <option value="PAYMAYA">PayMaya</option>
                                <option value="QRPH">QRPH</option>
                                <option value="BPI">BPI</option>
                                <option value="BDO">BDO</option>
                                <option value="METROBANK">Metrobank</option>
                                <option value="BANK_TRANSFER">Bank Transfer</option>
                                <option value="OTHERS">Others</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="customMopContainer" style="display: none;">
                            <label class="form-label small">Specify Other Payment Method <span class="text-danger">*</span></label>
                            <input type="text" name="custom_mop" id="customWalkinMop" class="form-control" placeholder="e.g. Maya, Palawan">
                        </div>
                        <div class="col-md-4" id="paymentProofContainer" style="display: none;">
                            <label class="form-label small">Proof of Payment (Image)</label>
                            <input type="file" name="proof_of_payment" class="form-control" accept="image/jpeg,image/png">
                            <div class="form-text">JPG or PNG, up to 2 MB.</div>
                        </div>
                    </div>

                    <hr class="my-2">

                    <div class="bg-light border rounded-3 p-2 mb-3">
                        <label for="walkInOrderSlip" class="form-label fw-bold">Order Slip Image</label>
                        <input type="file" id="walkInOrderSlip" name="order_slip" class="form-control" accept="image/jpeg,image/png,image/webp" aria-describedby="walkInOrderSlipHelp">
                        <div id="walkInOrderSlipHelp" class="form-text">JPG, PNG or WebP, up to 5 MB. Visible to inventory during review.</div>
                        <img id="walkInOrderSlipPreview" class="img-fluid rounded border mt-3 d-none" style="max-height: 100px;" alt="Selected order slip preview">
                    </div>
                    <!-- Product Items Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-secondary fw-bold mb-0"><i class="fa-solid fa-boxes-stacked me-1"></i> Order Items</h6>
                    </div>

                    <div id="walk-in-product-rows" tabindex="0" role="region" aria-label="Order items">
                        <div class="row g-2 align-items-center product-row mb-3">
                            <div class="col-md-5">
                                <label class="form-label small">Product <span class="text-danger">*</span></label>
                                <input type="search" class="form-control product-search" list="walkInProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                                <input type="hidden" name="items[0][product_id]" class="product-id">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Price (₱)</label>
                                <input type="number" step="0.01" name="items[0][price]" class="form-control price-input" readonly value="0.00">
                            </div>
                            <div class="col-md-1">
                                <label class="form-label small">Qty <span class="text-danger">*</span></label>
                                <input type="number" name="items[0][quantity]" class="form-control qty-input" value="1" min="1" required oninput="calculateTotals()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">Disc % (G)</label>
                                <input type="number" step="0.01" name="items[0][discount_percentage]" class="form-control discount-input" value="0.00" min="0" max="100" oninput="calculateTotals()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">&nbsp;</label>
                                <button type="button" class="btn btn-outline-danger btn-sm w-100 d-block" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addWalkInProductRow()">
                        <i class="fa-solid fa-plus me-1"></i> Add Another Item
                    </button>

                    <hr class="my-2">

                </div>
                <div class="modal-footer bg-light">
                    <div class="me-auto d-flex align-items-center gap-2">
                        <span class="fw-semibold">Total Amount:</span>
                        <span class="fw-bold text-success fs-5" id="walkInGrandTotalDisplay">₱0.00</span>
                        <input type="hidden" name="grand_total" id="walkInGrandTotalInput" value="0">
                    </div>                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Submit for Inventory Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts for Dynamic Table and Calculations -->
@php
    $walkInProductOptions = $products->where('status', 'active')->map(fn ($product) => [
        'id' => $product->id,
        'label' => ($product->item_id ?: $product->id).' — '.($product->name ?: $product->description ?: 'Unnamed product').' (Stock: '.$product->stock.')',
        'barcode' => $product->barcode,
        'description' => $product->description ?: $product->name,
        'price' => $product->sales_price ?? 0,
    ])->values();
@endphp
<script>
let walkInRowIdx = 0;
const walkInProductOptions = @json($walkInProductOptions);
const walkInLocation = document.getElementById('walkInLocation');
const walkInStoreHubId = document.getElementById('walkInStoreHubId');
const walkInDataList = document.createElement('datalist');
walkInDataList.id = 'walkInProductOptions';
walkInProductOptions.forEach(product => {
    const option = document.createElement('option');
    option.value = product.label;
    walkInDataList.appendChild(option);
});
document.body.appendChild(walkInDataList);
setupProductSuggestions(document.getElementById('walkInSaleModal'), walkInDataList, walkInProductOptions,
    () => walkInLocation.selectedOptions[0].dataset.productsUrl, product => ({
        id: product.id, label: `${product.item_id || product.id} — ${product.name || product.description || 'Unnamed product'} (Stock: ${product.stock})`,
        barcode: product.barcode, description: product.description || product.name, price: product.sales_price || 0,
    }), true);
const walkInCustomerName = document.getElementById('walkInCustomerName');
const walkInCustomerContact = document.getElementById('walkInCustomerContact');
const walkInCustomerAddress = document.getElementById('walkInCustomerAddress');
const walkInCustomerOptions = document.getElementById('existingWalkInCustomers');
let selectedWalkInCustomer = null;
async function loadWalkInCustomers() {
    walkInCustomerOptions.replaceChildren();
    try {
        const url = new URL(walkInLocation.selectedOptions[0].dataset.customersUrl, window.location.origin);
        url.searchParams.set('channel', 'walk_in');
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Customer lookup unavailable');
        const customers = await response.json();
        customers.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.name;
            option.dataset.contactNumber = customer.contact_number || '';
            option.dataset.address = customer.address || '';
            walkInCustomerOptions.appendChild(option);
        });
    } catch (error) {
        window.AppAlert?.show('Existing customers could not be loaded. You can still enter a new customer.', 'warning');
    }
}
document.getElementById('walkInSaleModal').addEventListener('show.bs.modal', () => {
    if (!walkInCustomerOptions.childElementCount) loadWalkInCustomers();
});
walkInLocation.addEventListener('change', () => {
    const selectedBranch = walkInLocation.selectedOptions[0];
    walkInStoreHubId.value = selectedBranch.dataset.hubId;
    walkInProductOptions.length = 0;
    walkInDataList.replaceChildren();
    document.querySelectorAll('#walk-in-product-rows .product-row').forEach(row => {
        row.querySelector('.product-search').value = '';
        row.querySelector('.product-search').setCustomValidity('');
        row.querySelector('.product-id').value = '';
        row.querySelector('.price-input').value = '0.00';
    });
    calculateTotals();
    walkInCustomerName.value = '';
    walkInCustomerContact.value = '';
    walkInCustomerAddress.value = '';
    selectedWalkInCustomer = null;
    loadWalkInCustomers();
});
walkInCustomerName.addEventListener('input', function () {
    const cursorPosition = this.selectionStart;
    const beforeCursor = this.value.slice(0, cursorPosition);
    this.value = this.value
        .toLowerCase()
        .replace(/(^|\s)([a-z])/g, (match, separator, letter) => separator + letter.toUpperCase());
    this.setSelectionRange(beforeCursor.length, beforeCursor.length);

    if (selectedWalkInCustomer && this.value !== selectedWalkInCustomer) {
        walkInCustomerContact.value = '';
        walkInCustomerAddress.value = '';
        selectedWalkInCustomer = null;
    }
});
walkInCustomerName.addEventListener('change', function () {
    const selected = [...walkInCustomerOptions.options].find(option => option.value === this.value);
    if (!selected) {
        selectedWalkInCustomer = null;
        return;
    }
    selectedWalkInCustomer = selected.value;
    walkInCustomerContact.value = selected.dataset.contactNumber || '';
    walkInCustomerAddress.value = selected.dataset.address || '';
});
let walkInOrderSlipUrl = null;
document.getElementById('walkInOrderSlip').addEventListener('change', function () {
    const preview = document.getElementById('walkInOrderSlipPreview');
    if (walkInOrderSlipUrl) URL.revokeObjectURL(walkInOrderSlipUrl);
    preview.classList.add('d-none');
    preview.removeAttribute('src');
    const file = this.files[0];
    this.setCustomValidity('');
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        this.setCustomValidity('Choose a JPG, PNG or WebP image up to 5 MB.');
        this.reportValidity();
        return;
    }
    walkInOrderSlipUrl = URL.createObjectURL(file);
    preview.src = walkInOrderSlipUrl;
    preview.classList.remove('d-none');
});

document.querySelectorAll('#walk-in-product-rows .product-row').forEach(row => {
    row.querySelector('.product-search').addEventListener('input', function () {
        updateRowPrice(this);
    });
});

function addWalkInProductRow() {
    walkInRowIdx++;
    const container = document.getElementById('walk-in-product-rows');
    const firstRow = container.querySelector('.product-row');
    const newRow = firstRow.cloneNode(true);

    // Update names and reset values for the cloned row
    newRow.querySelectorAll('input, select').forEach(element => {
        if(element.classList.contains('qty-input')) {
            element.value = '1';
        } else if(element.classList.contains('discount-input') || element.classList.contains('price-input')) {
            element.value = '0.00';
        } else if (element.classList.contains('product-search')) {
            element.value = '';
        } else {
            element.value = '';
        }
        
        let name = element.getAttribute('name');
        if (name) {
            element.setAttribute('name', name.replace(/\[\d+\]/, `[${walkInRowIdx}]`));
        }
    });

    container.appendChild(newRow);
    container.scrollTop = container.scrollHeight;
    const newSearch = newRow.querySelector('.product-search');
    if (newSearch) {
        newSearch.addEventListener('input', function () { updateRowPrice(this); });
        newSearch.focus({ preventScroll: true });
    }
}

function removeRow(button) {
    const rows = document.querySelectorAll('#walk-in-product-rows .product-row');
    if (rows.length > 1) {
        button.closest('.product-row').remove();
        calculateTotals();
    } else {
        AppAlert.show('You must have at least one product item.');
    }
}

function updateRowPrice(inputElement) {
    const row = inputElement.closest('.product-row');
    const value = inputElement.value.trim().toLowerCase();
    let matched = null;

    if (value.length > 0) {
        for (const item of walkInProductOptions) {
            if (item.label.toLowerCase() === value) { matched = item; break; }
        }
        // Keep barcode and description searchable without displaying them in the selected value.
        if (!matched) {
            matched = walkInProductOptions.find(item => {
                return [item.label, item.barcode, item.description]
                    .some(option => String(option || '').toLowerCase().includes(value));
            }) || null;
        }
    }

    row.querySelector('.product-id').value = matched ? matched.id : '';
    inputElement.setCustomValidity(matched ? '' : 'Select a product from the search suggestions.');
    const price = matched ? matched.price : 0;
    const priceInput = row.querySelector('.price-input');

    priceInput.value = parseFloat(price).toFixed(2);
    calculateTotals();
}

function calculateTotals() {
    let grandTotal = 0;
    document.querySelectorAll('#walk-in-product-rows .product-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const discountPercent = parseFloat(row.querySelector('.discount-input').value) || 0;

        const subtotal = qty * price;
        const discountAmount = subtotal * (discountPercent / 100);
        grandTotal += (subtotal - discountAmount);
    });

    document.getElementById('walkInGrandTotalDisplay').innerText = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('walkInGrandTotalInput').value = grandTotal.toFixed(2);
}

function handlePaymentMethodChange(select) {
    const value = select.value;
    const customMopContainer = document.getElementById('customMopContainer');
    const paymentProofContainer = document.getElementById('paymentProofContainer');
    const customWalkinMop = document.getElementById('customWalkinMop');

    if (value === 'OTHERS') {
        customMopContainer.style.display = 'block';
        customWalkinMop.setAttribute('required', 'required');
    } else {
        customMopContainer.style.display = 'none';
        customWalkinMop.removeAttribute('required');
        customWalkinMop.value = '';
    }

    if (value === 'CASH') {
        paymentProofContainer.style.display = 'none';
    } else {
        paymentProofContainer.style.display = 'block';
    }
}
</script>
