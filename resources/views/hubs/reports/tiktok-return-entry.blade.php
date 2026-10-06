@php
    $locked = $item->return_status === 'received';
    $editing = old('editing_item') == $item->id;
    $condition = $item->return_condition ?: 'good';
    $fieldValue = fn ($field, $default = null) => $editing ? old($field, $item->$field ?? $default) : ($item->$field ?? $default);
@endphp
<form method="POST" action="{{ route('hub.report.tiktok.return.update', ['hub' => $hub->id, 'transaction' => $transaction->id, 'item' => $item->id]) }}" class="border rounded p-3 mb-3" id="tiktok-return-item-{{ $item->id }}">
    @csrf
    @method('PATCH')
    <input type="hidden" name="editing_order" value="{{ $transaction->id }}">
    <input type="hidden" name="editing_item" value="{{ $item->id }}">
    <input type="hidden" name="return_status" value="received">
    <input type="hidden" name="refund_status" value="none">
    <input type="hidden" name="customer_refund_amount" value="0">
    <h6 class="fw-bold mb-3">{{ $item->product?->name ?? 'Product #'.$item->product_id }}</h6>
    <div class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label small" for="returned-quantity-{{ $item->id }}">Returned quantity (up to {{ $item->quantity }})</label>
            <input id="returned-quantity-{{ $item->id }}" class="form-control" type="number" name="returned_quantity" min="1" max="{{ $item->quantity }}" value="{{ $locked ? $item->returned_quantity : $fieldValue('returned_quantity', 1) }}" required @readonly($locked)>
        </div>
        <div class="col-md-4">
            <label class="form-label small" for="return-condition-{{ $item->id }}">Item status</label>
            <select id="return-condition-{{ $item->id }}" class="form-select" name="return_condition" @disabled($locked)>
                <option value="good" @selected($fieldValue('return_condition', $condition) === 'good')>Good</option>
                <option value="damaged" @selected($fieldValue('return_condition', $condition) === 'damaged')>Bad / damaged</option>
            </select>
            @if($locked)
                <input type="hidden" name="return_condition" value="{{ $item->return_condition }}">
            @endif
        </div>
        <div class="col-md-4">
            <label class="form-label small" for="return-notes-{{ $item->id }}">Notes</label>
            <textarea id="return-notes-{{ $item->id }}" class="form-control" name="return_reason" maxlength="2000" rows="2">{{ $fieldValue('return_reason') }}</textarea>
        </div>
    </div>
    <p class="small text-muted mt-3 mb-2">{{ $locked ? 'Item already received. You can update the notes.' : 'Good items are restored to TikTok stock. Bad items are added to physical stock only.' }}</p>
    <button class="btn btn-primary btn-sm" type="submit">{{ $locked ? 'Update notes' : 'Save returned item' }}</button>
</form>
