
                @php
                    $locked = $item->return_status === 'received';
                    $editing = old('editing_item') == $item->id;
                    $fieldValue = fn ($field, $default = null) => $editing ? old($field, $item->$field ?? $default) : ($item->$field ?? $default);
                @endphp
                <form data-return-form method="POST" action="{{ route('hub.report.tiktok.return.update', ['hub' => $hub->id, 'transaction' => $transaction->id, 'item' => $item->id]) }}" class="border rounded p-3 mt-3">
                    @csrf @method('PATCH')
                    <input type="hidden" name="editing_order" value="{{ $transaction->id }}"><input type="hidden" name="editing_item" value="{{ $item->id }}">
                    <h6 class="fw-bold mb-3">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</h6>
                    <div class="row g-3">
                        @foreach(['return_status' => ['What happened?', $returnLabels], 'return_condition' => ['Item condition', ['' => 'Not received / not applicable', 'good' => 'Good — restore sellable stock', 'damaged' => 'Damaged — do not restock']], 'refund_status' => ['Refund progress', $refundLabels]] as $field => [$label, $options])
                        <div class="col-md-4"><label class="form-label small" for="{{ $field }}-{{ $item->id }}">{{ $label }}</label><select id="{{ $field }}-{{ $item->id }}" name="{{ $field }}" class="form-select" @disabled($locked && $field !== 'refund_status')>@foreach($options as $value => $text)<option value="{{ $value }}" @selected($fieldValue($field, $field === 'return_condition' ? '' : 'none') === $value)>{{ $text }}</option>@endforeach</select>@if($locked && $field !== 'refund_status')<input type="hidden" name="{{ $field }}" value="{{ $item->$field }}">@endif</div>
        
                        @endforeach
                        <div class="col-md-4"><label class="form-label small" for="qty-{{ $item->id }}">Return quantity (up to {{ $item->quantity }})</label><input id="qty-{{ $item->id }}" class="form-control" type="number" name="returned_quantity" min="0" max="{{ $item->quantity }}" value="{{ $locked ? $item->returned_quantity : $fieldValue('returned_quantity', 0) }}" required @readonly($locked)><div class="form-text">Use 0 for refund-only requests.</div></div>
                        <div class="col-md-4"><label class="form-label small" for="refund-{{ $item->id }}">Customer refund (₱)</label><input id="refund-{{ $item->id }}" class="form-control tiktok-refund-amount" type="number" step="0.01" name="customer_refund_amount" min="0" max="{{ $item->line_total }}" value="{{ $fieldValue('customer_refund_amount', 0) }}" data-unit-price="{{ $item->unit_price }}" data-discount="{{ $item->discount_percentage ?? 0 }}" data-quantity="#qty-{{ $item->id }}"><div class="form-text">For received returns, this is calculated from the discounted price × returned quantity.</div></div>
                        <div class="col-md-4"><label class="form-label small" for="reason-{{ $item->id }}">Reason (optional)</label><textarea id="reason-{{ $item->id }}" class="form-control" name="return_reason" maxlength="2000" rows="2">{{ $fieldValue('return_reason') }}</textarea></div>
                    </div>
                    <p class="small text-muted mt-3">{{ $locked ? 'Item received. You can still update the refund.' : 'Only good items received back will be added to stock.' }}</p>
                    <button class="btn btn-outline-primary" type="submit">Save changes</button>
                </form>
                <script>
                document.querySelectorAll('[data-return-form]').forEach(form => {
                    const status = form.querySelector('[name="return_status"]');
                    const quantity = form.querySelector('[name="returned_quantity"]');
                    const refund = form.querySelector('.tiktok-refund-amount');
                    if (!status || !quantity || !refund) return;
                    const updateRefund = () => {
                        const unitPrice = Number(refund.dataset.unitPrice || 0);
                        const discount = Number(refund.dataset.discount || 0);
                        const returnedQuantity = Math.max(0, Number(quantity.value || 0));
                        const automaticAmount = Math.round(unitPrice * (1 - discount / 100) * returnedQuantity * 100) / 100;
                        if (status.value === 'received' && returnedQuantity > 0) {
                            refund.value = automaticAmount.toFixed(2);
                            refund.readOnly = true;
                        } else {
                            refund.readOnly = false;
                        }
                    };
                    status.addEventListener('change', updateRefund);
                    quantity.addEventListener('input', updateRefund);
                    updateRefund();
                });
                </script>
