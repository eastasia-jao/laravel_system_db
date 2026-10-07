@php($exchangeId = 'exchange-lines-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $exchangePrefix))
<div id="{{ $exchangeId }}" class="mt-3" data-exchange-lines data-search-url="{{ route('hub.products.search.ajax', $hub->id) }}" data-stock-channel="{{ $exchangeChannel }}">
    <div class="alert alert-warning small mb-3">
        @if($exchangeChannel === 'online' || ($exchangeChannel === 'walk_in' && $hub->is_head_office))
            If the replacement basket exceeds the exchange credit, only the excess is an additional payment. An equal or lower total requires no additional payment.
        @else
            The returned item's actual paid value is non-refundable exchange credit. Add products until the replacement basket equals or exceeds that credit. Any excess becomes an additional payment.
        @endif
    </div>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label fw-semibold mb-0">Additional replacement products</label>
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-exchange-line><i class="fa-solid fa-plus me-1"></i>Add product</button>
    </div>
    <div data-exchange-line-list></div>
    <template data-exchange-line-template>
        <div class="border rounded-3 p-3 mb-2" data-exchange-line>
            <div class="d-flex justify-content-between gap-2 mb-2"><strong class="small">Additional item</strong><button type="button" class="btn btn-sm btn-outline-danger" data-remove-exchange-line aria-label="Remove additional item"><i class="fa-solid fa-trash"></i></button></div>
            <div class="row g-2">
                <div class="col-12"><input type="text" class="form-control form-control-sm" data-exchange-search autocomplete="off" placeholder="Search Item ID, barcode, or product name" required><input type="hidden" data-exchange-product></div>
                <div class="col-6"><label class="form-label small mb-1">Quantity</label><input type="number" class="form-control form-control-sm" data-exchange-quantity min="1" value="1" required></div>
                <div class="col-6"><label class="form-label small mb-1">Discount %</label><input type="number" class="form-control form-control-sm" data-exchange-discount min="0" max="100" step="0.01" value="0"></div>
                <div class="col-12"><div class="form-text" data-exchange-help>Select a product from the suggestions.</div></div>
            </div>
        </div>
    </template>
    <div class="card border-primary-subtle bg-primary-subtle mt-3" @if($exchangeChannel === 'walk_in') data-walk-in-additional-payment @endif>
        <div class="card-body">
            <div class="fw-semibold text-primary mb-1"><i class="fa-solid fa-money-bill-transfer me-1"></i>Additional payment</div>
            <p class="small text-muted mb-3">The amount is calculated from the replacement basket and exchange credit. Any additional payment is posted only after inventory approval.</p>
            <div class="row g-2">
                <div class="col-md-4"><label class="form-label small fw-semibold">Amount</label><input type="number" name="exchange_payment_amount" class="form-control" min="0" step="0.01" value="0.00"></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Mode of payment</label><select name="exchange_payment_method" class="form-select" data-exchange-mop><option value="">Select MOP</option><option value="CASH">Cash</option><option value="GCASH">GCash</option><option value="PAYMAYA">PayMaya</option>@if($exchangeChannel === 'walk_in')<option value="QRPH">QRPH</option><option value="BPI">BPI</option><option value="BDO">BDO</option><option value="METROBANK">Metrobank</option>@endif<option value="BANK_TRANSFER">Bank transfer</option>@unless($exchangeChannel === 'walk_in')<option value="DATED_CHECK">Dated check</option><option value="POST_DATED_CHECK">Post-dated check</option><option value="COD">COD</option>@endunless<option value="OTHERS">Other (Specify MOP)</option></select></div>
                <div class="col-md-4"><label class="form-label small fw-semibold">Reference / details</label><input type="text" name="exchange_payment_reference" class="form-control" maxlength="255" placeholder="Receipt, transfer, check, or platform reference"></div>
                <div class="col-md-4 d-none" data-exchange-custom-mop-wrap><label class="form-label small fw-semibold">Specify other MOP</label><input type="text" name="exchange_custom_mop" class="form-control" maxlength="100" placeholder="Enter payment method" disabled></div>
                <div class="col-md-4 d-none" data-exchange-bank-wrap><label class="form-label small fw-semibold">Bank name</label><select name="exchange_bank_name" class="form-select" disabled><option value="">Select bank</option><option value="BPI">BPI</option><option value="BDO">BDO</option><option value="METROBANK">Metrobank</option><option value="UNIONBANK">UnionBank</option><option value="SECURITY_BANK">Security Bank</option><option value="OTHERS">Other bank</option></select></div>
                <div class="col-md-4 d-none" data-exchange-custom-bank-wrap><label class="form-label small fw-semibold">Specify bank name</label><input type="text" name="exchange_custom_bank_name" class="form-control" maxlength="100" placeholder="Enter bank name" disabled></div>
                @unless($exchangeChannel === 'walk_in')
                    <div class="col-md-4 d-none" data-exchange-check-wrap><label class="form-label small fw-semibold">Check number</label><input type="text" name="exchange_check_number" class="form-control" maxlength="255" placeholder="Enter check number" disabled></div>
                    <div class="w-100 d-none" data-exchange-proof-row-break></div>
                    <div class="col-md-4 d-none" data-exchange-check-wrap><label class="form-label small fw-semibold">Check date</label><input type="date" name="exchange_check_date" class="form-control" disabled></div>
                @endunless
                <div class="col-12" data-exchange-proof-wrap><label class="form-label small fw-semibold">Additional payment proof / attachment</label><input type="file" name="exchange_payment_proofs[]" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf" multiple><div class="form-text">Required for non-cash additional payments. You may attach multiple files, up to 4 files at 2 MB each.</div></div>
            </div>
        </div>
    </div>
</div>
@once
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-exchange-lines]').forEach(root => {
        const list = root.querySelector('[data-exchange-line-list]');
        const template = root.querySelector('[data-exchange-line-template]');
        const notifyChanged = () => root.dispatchEvent(new CustomEvent('exchange:changed', { bubbles: true }));
        const mop = root.querySelector('[data-exchange-mop]');
        const customMopWrap = root.querySelector('[data-exchange-custom-mop-wrap]');
        const bankWrap = root.querySelector('[data-exchange-bank-wrap]');
        const bank = root.querySelector('[name="exchange_bank_name"]');
        const customBankWrap = root.querySelector('[data-exchange-custom-bank-wrap]');
        const checkWraps = root.querySelectorAll('[data-exchange-check-wrap]');
        const proofWrap = root.querySelector('[data-exchange-proof-wrap]');
        const proofRowBreak = root.querySelector('[data-exchange-proof-row-break]');
        const toggleField = (wrap, visible) => {
            wrap?.classList.toggle('d-none', !visible);
            wrap?.querySelectorAll('input, select').forEach(field => {
                field.disabled = !visible;
                field.required = visible;
                if (!visible) field.value = '';
            });
        };
        const updatePaymentFields = () => {
            const method = mop?.value || '';
            const isCheck = ['DATED_CHECK', 'POST_DATED_CHECK'].includes(method);
            toggleField(customMopWrap, method === 'OTHERS');
            toggleField(bankWrap, method === 'BANK_TRANSFER' || isCheck);
            toggleField(customBankWrap, (method === 'BANK_TRANSFER' || isCheck) && bank?.value === 'OTHERS');
            checkWraps.forEach(wrap => toggleField(wrap, isCheck));
            proofRowBreak?.classList.toggle('d-none', !isCheck);
            proofWrap?.classList.toggle('col-md-8', isCheck || method === 'OTHERS');
        };
        mop?.addEventListener('change', updatePaymentFields);
        bank?.addEventListener('change', updatePaymentFields);
        root.closest('.modal')?.addEventListener('show.bs.modal', () => {
            mop.value = '';
            root.querySelector('[name="exchange_payment_reference"]').value = '';
            root.querySelector('[name="exchange_payment_proofs[]"]').value = '';
            updatePaymentFields();
        });
        updatePaymentFields();
        let nextIndex = 0;
        root.querySelector('[data-add-exchange-line]').addEventListener('click', () => {
            const row = template.content.firstElementChild.cloneNode(true);
            const index = nextIndex++;
            const search = row.querySelector('[data-exchange-search]');
            const product = row.querySelector('[data-exchange-product]');
            const quantity = row.querySelector('[data-exchange-quantity]');
            const discount = row.querySelector('[data-exchange-discount]');
            const help = row.querySelector('[data-exchange-help]');
            product.name = `additional_items[${index}][product_id]`;
            quantity.name = `additional_items[${index}][quantity]`;
            discount.name = `additional_items[${index}][discount_percentage]`;
            let timer;
            let choices = new Map();
            search.addEventListener('input', () => {
                product.value = '';
                product.dataset.unitPrice = '0';
                notifyChanged();
                clearTimeout(timer);
                const query = search.value.trim();
                if (query.length < 1) return;
                timer = setTimeout(async () => {
                    const url = new URL(root.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    url.searchParams.set('active_only', '1');
                    url.searchParams.set('stock_channel', root.dataset.stockChannel);
                    const results = await fetch(url, { headers: { Accept: 'application/json' } }).then(response => response.ok ? response.json() : []);
                    choices = new Map();
                    const dataList = document.createElement('datalist');
                    dataList.id = `exchange-options-${root.id}-${index}`;
                    results.forEach(item => {
                        const barcode = item.barcode ? ` · Barcode: ${item.barcode}` : '';
                        const label = `${item.item_id || item.id} — ${item.name || 'Unnamed product'}${barcode}`;
                        choices.set(label, item);
                        const option = document.createElement('option'); option.value = label; dataList.appendChild(option);
                    });
                    row.querySelector('datalist')?.remove();
                    row.appendChild(dataList); search.setAttribute('list', dataList.id);
                }, 200);
            });
            search.addEventListener('change', () => {
                const selected = choices.get(search.value);
                product.value = selected?.id || '';
                product.dataset.unitPrice = selected
                    ? Number(root.dataset.stockChannel === 'wholesale' ? (selected.wholesale_price || selected.sales_price || 0) : (selected.sales_price || 0))
                    : 0;
                const available = Number(selected?.channel_available_stock ?? selected?.stock ?? 0);
                const displayedPrice = Number(root.dataset.stockChannel === 'wholesale' ? (selected?.wholesale_price || selected?.sales_price || 0) : (selected?.sales_price || 0));
                help.textContent = selected ? `${available} unit(s) available · ₱${displayedPrice.toLocaleString('en-PH', {minimumFractionDigits: 2})}` : 'Select a product from the suggestions.';
                notifyChanged();
            });
            quantity.addEventListener('input', notifyChanged);
            discount.addEventListener('input', notifyChanged);
            row.querySelector('[data-remove-exchange-line]').addEventListener('click', () => { row.remove(); notifyChanged(); });
            list.appendChild(row); notifyChanged(); search.focus();
        });
    });
});
</script>
@endonce
