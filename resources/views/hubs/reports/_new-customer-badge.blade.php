@if(in_array((int) $transaction->id, $newCustomerSaleIds, true))
    <span class="badge rounded-pill new-customer-badge ms-1" title="First order in this sales channel">NEW</span>
@endif
