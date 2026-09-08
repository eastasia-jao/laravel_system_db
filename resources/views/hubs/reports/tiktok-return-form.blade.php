
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
                        <div class="col-md-4"><label class="form-label small" for="refund-{{ $item->id }}">Customer refund (₱)</label><input id="refund-{{ $item->id }}" class="form-control" type="number" step="0.01" name="customer_refund_amount" min="0" max="{{ $item->line_total }}" value="{{ $fieldValue('customer_refund_amount', 0) }}"><div class="form-text">Only completed refunds affect net payout.</div></div>
                        <div class="col-md-4"><label class="form-label small" for="reason-{{ $item->id }}">Reason (optional)</label><textarea id="reason-{{ $item->id }}" class="form-control" name="return_reason" maxlength="2000" rows="2">{{ $fieldValue('return_reason') }}</textarea></div>
                    </div>
                    <p class="small text-muted mt-3">{{ $locked ? 'Item received. You can still update the refund.' : 'Only good items received back will be added to stock.' }}</p>
                    <button class="btn btn-outline-primary" type="submit">Save changes</button>
                </form>
