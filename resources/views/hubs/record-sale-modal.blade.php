<style>
    #recordSaleModal .modal-dialog { max-width: 1140px; }
    #recordSaleModal .modal-content { border: 0; border-radius: 18px; overflow: hidden; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); }
    #recordSaleForm { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    #recordSaleModal .modal-header, #recordSaleModal .modal-footer { padding: .9rem 1.25rem; }
    #recordSaleModal .modal-header { background: linear-gradient(135deg, #fff, #f8fbff); border-bottom: 1px solid #dbeafe; }
    #recordSaleModal .modal-title { font-size: 1.05rem; color: #253247; }
    #recordSaleModal .modal-body { padding: 1.25rem; background: #f8fafc; }
    #recordSaleModal .form-label { font-size: .8rem; margin-bottom: .3rem; font-weight: 500 !important; }
    #recordSaleModal .form-control, #recordSaleModal .form-select { font-size: .85rem; min-height: 36px; border-color: #dce1e7; border-radius: .4rem; }
    #recordSaleModal h6 { font-size: .875rem; color: #374151 !important; }
    #recordSaleModal hr { margin: 1rem 0 !important; color: #dce1e7; opacity: 1; }
    #recordSaleModal .extra-fields-container { padding: 1rem; margin-top: 1rem !important; background: #fff !important; border: 1px solid #dbe5ef; border-radius: 16px !important; }
    #recordSaleModal .extra-fields-container .shadow-sm { box-shadow: none !important; }
    #recordSaleModal .channel-detail-panel { padding: 1.25rem; border: 1px solid #dce5ef; border-radius: 14px; background: linear-gradient(145deg, #fff, #f8fbff); box-shadow: 0 5px 18px rgba(15, 23, 42, .045); }
    #recordSaleModal .channel-detail-header { display: flex; align-items: flex-start; gap: .8rem; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid #e8eef5; }
    #recordSaleModal .channel-detail-icon { display: inline-flex; width: 40px; height: 40px; flex: 0 0 40px; align-items: center; justify-content: center; border-radius: 12px; background: #eff6ff; color: #2563eb; }
    #recordSaleModal .channel-detail-title { margin: 0; color: #172b4d; font-size: .98rem; font-weight: 750; }
    #recordSaleModal .channel-detail-help { margin: .2rem 0 0; color: #64748b; font-size: .78rem; }
    #recordSaleModal .sale-field-heading { margin: .25rem 0 -.1rem; padding-bottom: .45rem; border-bottom: 1px solid #e8eef5; color: #475569; font-size: .72rem; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; }
    #recordSaleModal .sale-summary-card { height: 100%; padding: .8rem .9rem; border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; }
    #recordSaleModal .sale-summary-card .form-control { border: 0; padding-left: 0; padding-right: 0; box-shadow: none; }
    #recordSaleModal .sale-summary-card-highlight { border-color: #bbf7d0; background: linear-gradient(145deg, #f0fdf4, #fff); }
    #recordSaleModal .sale-summary-card-highlight .form-control { color: #15803d !important; font-size: 1.15rem; }
    #recordSaleModal .sale-summary-card { min-height: 96px; display: flex; flex-direction: column; justify-content: center; }
    #recordSaleModal .extra-fields-container .row > [class*="col-"] { min-width: 0; }
    #recordSaleModal .modal-footer { position: sticky; bottom: 0; z-index: 3; background: rgba(255,255,255,.96); border-top: 1px solid #dbe5ef; box-shadow: 0 -8px 22px rgba(15, 23, 42, .05); }
    #recordSaleModal .online-reconciliation-grid .sale-summary-card { display: flex; flex-direction: column; justify-content: center; min-height: 84px; }
    #recordSaleModal .online-reconciliation-grid .sale-summary-card .form-control { min-height: 30px; font-weight: 700; font-variant-numeric: tabular-nums; }
    #recordSaleModal .online-reconciliation-grid .sale-summary-card-highlight .form-control { font-size: 1.3rem; }
    #recordSaleModal .online-remarks-panel { padding: .9rem 1rem; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; }
    #recordSaleModal .online-remarks-panel .form-control { min-height: 72px; resize: vertical; }
    #recordSaleModal .sale-attachment-field { padding: .85rem; border: 1px dashed #cbd5e1; border-radius: 11px; background: #fff; }
    #recordSaleModal .sale-attachment-field .form-text { max-width: 58ch; }
    #recordSaleModal #wholesaleExtraFields > .border { padding: 0 !important; border: 0 !important; background: transparent !important; }
    #recordSaleModal #wholesaleExtraFields .row,
    #recordSaleModal #onlineExtraFields .row { --bs-gutter-x: 1rem; --bs-gutter-y: .9rem; }
    #recordSaleModal #wholesaleExtraFields .check-details-fields { margin: 0; }
    #recordSaleModal #wholesaleExtraFields .wholesale-adjustment-card { padding: .9rem; border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; height: 100%; }
    #recordSaleModal #wholesaleExtraFields .wholesale-adjustment-card .form-text { margin-bottom: 0; }
    #recordSaleModal #wholesaleExtraFields .sale-attachment-field { padding: .7rem; }
    #recordSaleModal #wholesaleExtraFields .sale-attachment-field .form-text { font-size: .75rem; line-height: 1.3; }
    #recordSaleModal #wholesaleExtraFields .sale-attachment-field .form-label { font-size: .82rem; margin-bottom: .35rem; }
    #recordSaleModal #wholesaleExtraFields .wholesale-payment-guide { display: flex; align-items: center; min-height: 72px; padding: .85rem 1rem; border: 1px solid #dbeafe; border-radius: 11px; background: #eff6ff; color: #475569; font-size: .8rem; line-height: 1.45; }
    #recordSaleModal #wholesaleExtraFields .wholesale-payment-guide i { color: #2563eb; font-size: 1rem; margin-right: .65rem; }
    #recordSaleModal #onlineExtraFields { margin-top: 1rem !important; }
    #recordSaleModal #onlineExtraFields .form-control[readonly] { background-color: #f8fafc !important; }
    #productRowsContainer { max-height: 230px; overflow-y: auto; overflow-x: hidden; scrollbar-gutter: stable; padding: 0 .75rem .5rem; border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; }
    #productRowsContainer > .row:first-child { position: sticky; top: 0; z-index: 2; background: #f8fafb; padding-top: .65rem; padding-bottom: .65rem; font-size: .75rem; }
    #productRowsContainer .product-row { margin-bottom: .5rem !important; padding-bottom: .5rem !important; }
    #productRowsContainer .product-row:last-child { border-bottom: 0 !important; }
    #productRowsContainer .item-price-display { color: #475569 !important; font-weight: 500 !important; }
    #recordSaleModal .marketplace-mop-menu { max-height: 210px; overflow-y: auto; min-width: 100%; scrollbar-gutter: stable; }
    #recordSaleModal .marketplace-mop-menu .dropdown-item { font-size: .85rem; padding: .55rem .75rem; }
    #recordSaleModal .marketplace-mop-dropdown .marketplace-mop-menu {
        top: calc(100% + 4px) !important;
        bottom: auto !important;
        transform: none !important;
    }
    @media (max-width: 767.98px) {
        #recordSaleModal .modal-dialog { max-width: none; min-height: 100dvh; margin: 0; }
        #recordSaleModal .modal-content { min-height: 100dvh; border-radius: 0; }
        #recordSaleModal .modal-header { padding: .8rem 1rem; }
        #recordSaleModal .modal-body { padding: .75rem; }
        #recordSaleModal .extra-fields-container, #recordSaleModal .channel-detail-panel { padding: .85rem; border-radius: 12px !important; }
        #recordSaleModal .channel-detail-header { margin-bottom: 1rem; padding-bottom: .8rem; }
        #recordSaleModal .channel-detail-icon { width: 34px; height: 34px; flex-basis: 34px; border-radius: 10px; }
        #recordSaleModal .channel-detail-panel .row { --bs-gutter-x: .7rem; --bs-gutter-y: .7rem; }
        #recordSaleModal .channel-detail-panel .row > [class*="col-md-"],
        #recordSaleModal .channel-detail-panel .row > [class*="col-lg-"] { width: 100%; }
        #recordSaleModal .sale-summary-card { min-height: 78px; }
        #recordSaleModal .modal-footer { padding: .75rem 1rem; }
        #recordSaleModal .modal-footer .btn { flex: 1 1 auto; }
        #productRowsContainer { max-height: 300px; padding-top: .75rem; }
        #recordSaleModal .modal-footer { gap: .5rem; }
    }
</style>

@php
    $channelOptions = [
        'shopee' => 'Shopee Sales',
        'lazada' => 'Lazada Sales',
        'tiktok' => 'TikTok Sales',
        'wholesale' => 'Wholesale Sales',
        'online' => 'Online Orders',
        'walk_in' => 'Walk-In Sales',
    ];
    $assignedChannels = auth()->user()?->usesAssignedSalesChannels()
        ? collect(auth()->user()->sales_channels ?? [])
            ->map(fn ($channel) => strtolower(str_replace(['-', ' '], '_', trim((string) $channel))))
            ->filter(fn ($channel) => array_key_exists($channel, $channelOptions))
            ->unique()
            ->values()
        : collect();
    $singleAssignedChannel = $assignedChannels->count() === 1 ? $assignedChannels->first() : null;
@endphp

<div class="modal fade" id="recordSaleModal" tabindex="-1" aria-labelledby="recordSaleModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form action="{{ route('sales.storeMultiChannelSale') }}" method="POST" enctype="multipart/form-data" id="recordSaleForm">
                @csrf
                <input type="hidden" name="store_hub_id" value="{{ $hub->id }}">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="recordSaleModalLabel">
                        <i class="fa-solid fa-cart-shopping me-2"></i> Record Multi-Channel Sale
                    </h5>
                </div>
                
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- Sales Channel -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sales Channel</label>
                            <select name="channel_type" id="channelTypeSelect" class="form-select" required onchange="handleChannelChange()" @if($singleAssignedChannel) style="display: none;" @endif>
                                <option value="">-- Select Channel --</option>
                                @foreach($channelOptions as $channelValue => $channelLabel)
                                    @if(!auth()->user()?->usesAssignedSalesChannels() || auth()->user()->hasSalesChannel($channelValue))
                                        <option value="{{ $channelValue }}" @selected($singleAssignedChannel === $channelValue)>{{ $channelLabel }}</option>
                                    @endif
                                @endforeach
                            </select>
                            @if($singleAssignedChannel)
                                <div class="form-control bg-light text-dark fw-semibold">{{ $channelOptions[$singleAssignedChannel] }}</div>
                            @endif
                        </div>

                        <!-- Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-2" id="platformOrderNumberField" style="display: none;">
                                <label class="form-label fw-bold" id="platformOrderNumberLabel">Platform Order Number</label>
                                <input type="text" name="order_number" class="form-control" placeholder="Enter the platform order number" disabled>
                                <div class="form-text" id="platformOrderNumberHelp"></div>
                            </div>
                            <div class="mb-2" id="automaticOrderNumberField">
                                <label class="form-label fw-bold">Order Number</label>
                                <div class="form-control bg-light text-dark fw-semibold" id="automaticOrderNumberValue">Select a sales channel</div>
                            </div>
                        </div>

                        <!-- Customer Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Customer Name</label>
                            <input type="text" name="customer_name" id="saleCustomerName" class="form-control customer-name-input" list="existingSaleCustomers" placeholder="Search existing customer or type a new name" autocomplete="name" required>
                            <datalist id="existingSaleCustomers"></datalist>
                            <div class="form-text">Choose an existing customer or type a new customer name.</div>
                        </div>
                    <hr class="my-4">

                    <!-- Multiple Item Details Section -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold text-secondary m-0"><i class="fa-solid fa-boxes-stacked me-1"></i> Order Items</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="addItemRowBtn">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Item
                        </button>
                    </div>

                    <!-- Product Rows Container -->
                    <div id="productRowsContainer" tabindex="0" role="region" aria-label="Sale product items">
                        <!-- Header Labels for Grid Alignment -->
                        <div class="row g-2 fw-bold text-secondary small mb-2 d-none d-md-flex">
                            <div class="col-md-3">Product Description</div>
                            <div class="col-md-1">Qty</div>
                            <div class="col-md-2">Regular Price</div>
                            <div class="col-md-1 text-danger">Disc %</div>
                            <div class="col-md-2 text-success">Total after Disc.</div>
                            <div class="col-md-2 text-info tiktok-shipping-fee-column">Shipping Service Fee</div>
                            <div class="col-md-1 text-center">Action</div>
                        </div>

                        <!-- Initial Product Row -->
                        <div class="row g-2 align-items-end product-row mb-3 pb-3 border-bottom" data-index="0">
                            <!-- Product Description -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold d-md-none">Product Description</label>
                                <input type="search" class="form-control product-search" list="saleProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                                <input type="hidden" name="items[0][product_id]" class="product-id">
                            </div>

                            <!-- Quantity -->
                            <div class="col-md-1">
                                <label class="form-label fw-bold d-md-none">Quantity</label>
                                <input type="number" name="items[0][quantity]" class="form-control qty-input calc-trigger" min="1" value="1" required>
                            </div>

                            <!-- Regular Price -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold d-md-none">Regular Price</label>
                                <input type="text" class="form-control item-price-display bg-white fw-bold text-primary" readonly value="₱0.00">
                                <input type="hidden" name="items[0][unit_price]" class="item-unit-price-input" value="0">
                            </div>

                            <!-- Discount % -->
                            <div class="col-md-1">
                                <label class="form-label fw-bold text-danger d-md-none">Discount %</label>
                                <input type="number" step="0.01" name="items[0][discount_percentage]" class="form-control item-discount-input calc-trigger" value="0.00" placeholder="0.00">
                            </div>

                            <!-- Total after Disc. -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-success d-md-none">Total after Disc.</label>
                                <input type="text" class="form-control item-total-display bg-white fw-bold text-success" readonly value="₱0.00">
                            </div>

                            <div class="col-md-2 tiktok-shipping-fee-column">
                                <label class="form-label fw-bold text-info d-md-none">Shipping Service Fee</label>
                                <input type="text" class="form-control item-shipping-service-fee-display bg-white fw-bold text-info" readonly value="₱0.00">
                            </div>

                            <!-- Remove Row Button -->
                            <div class="col-md-1">
                                <label class="form-label fw-bold d-md-none">Action</label>
                                <button type="button" class="btn btn-outline-danger w-100 remove-row-btn" title="Delete Item">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- EXTRA FIELDS FOR SHOPEE & LAZADA -->
                    <div id="marketplaceExtraFields" class="p-3 bg-light rounded border extra-fields-container" style="display: none;">
                        <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-store me-1"></i> Shopee / Lazada Tracking Details</h6>
                        
                        <div class="row g-3 align-items-start">
                            <div class="col-md-8">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Date of Arrangement</label>
                                        <input type="date" name="date_of_arrangement" class="form-control bg-white market-input" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Address</label>
                                        <input type="text" name="address" class="form-control bg-white market-input" placeholder="Enter delivery address" disabled>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">MOP</label>
                                        <select name="mode_of_payment" class="d-none market-input mop-select" id="mopSelect" disabled>
                                            <option value="">-- Select MOP --</option>
                                            <option value="COD">COD</option>
                                            <option value="SPAYLATER" data-market-channel="shopee">SPayLater</option>
                                            <option value="PAYLATER" data-market-channel="lazada">PayLater</option>
                                            <option value="MIXEDCARD">Mixedcard</option>
                                            <option value="CREDIT_DEBIT_CARD">Credit/Debit Card</option>
                                            <option value="GCASH">GCASH</option>
                                            <option value="SHOPEEPAY_BALANCE" data-market-channel="shopee">ShopeePay Balance</option>
                                            <option value="ONLINE_OFFLINE_PAYMENT">Online/Offline Payment</option>
                                            <option value="QRPH">QRph</option>
                                            <option value="OTHERS">Others</option>
                                        </select>
                                        <div class="dropdown marketplace-mop-dropdown">
                                            <button type="button" class="btn btn-outline-secondary bg-white text-dark dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" id="marketplaceMopButton" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">-- Select MOP --</button>
                                            <div class="dropdown-menu marketplace-mop-menu w-100" aria-labelledby="marketplaceMopButton">
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="COD">COD</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="SPAYLATER" data-market-channel="shopee">SPayLater</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="PAYLATER" data-market-channel="lazada">PayLater</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="MIXEDCARD">Mixedcard</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="CREDIT_DEBIT_CARD">Credit/Debit Card</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="GCASH">GCASH</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="SHOPEEPAY_BALANCE" data-market-channel="shopee">ShopeePay Balance</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="ONLINE_OFFLINE_PAYMENT">Online/Offline Payment</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="QRPH">QRph</button>
                                                <button type="button" class="dropdown-item marketplace-mop-option" data-value="OTHERS">Others</button>
                                            </div>
                                        </div>
                                        <input type="text" name="mode_of_payment_others" id="mopOthersInput" class="form-control bg-white mt-2 market-input" placeholder="Specify MOP" style="display: none;" disabled>
                                    </div>

                                </div>
                            </div>

                            <!-- Total Amount Card Column -->
                            <div class="col-md-4">
                                <div class="bg-white border rounded-3 p-3 text-end shadow-sm" style="max-width: 220px; margin-left: auto;">
                                    <span class="text-muted text-uppercase fw-bold d-block" style="font-size: 10px; letter-spacing: 0.5px;">Total Amount</span>
                                    <input type="hidden" id="shopeeSubTotal" value="0.00">
                                    <input type="text" id="shopeeGrandTotal" class="form-control-plaintext text-end fs-4 fw-bold text-success p-0 m-0" value="₱0.00" readonly style="color: #198754 !important;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Wholesale Extra Fields Container -->
                    <div id="wholesaleExtraFields" class="extra-fields-container" style="display: none;">
                        <div class="channel-detail-panel">
                            <div class="channel-detail-header">
                                <span class="channel-detail-icon"><i class="fa-solid fa-handshake"></i></span>
                                <div><h6 class="channel-detail-title">Wholesale order details</h6><p class="channel-detail-help">Payment, delivery, and order adjustments for this customer.</p></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 sale-field-heading">Payment details</div>
                                <!-- Mode of Payment -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Mode of Payment (MOP)</label>
                                    <select name="mode_of_payment" class="form-select wholesale-mop-select" required>
                                        <option value="" selected disabled>-- Select MOP --</option>
                                        <option value="CASH">Cash</option>
                                        <option value="BANK_TRANSFER">Bank Transfer</option>
                                        <option value="DATED_CHECK">Dated Check</option>
                                        <option value="POST_DATED_CHECK">Post-Dated Check</option>
                                        <option value="OTHERS">Others</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="wholesale-payment-guide" data-wholesale-payment-guide><i class="fa-solid fa-circle-info"></i><span>Select a payment method to show its required payment details and proof.</span></div>
                                </div>

                                <!-- Custom MOP Input (Shown when MOP is OTHERS) -->
                                <div class="col-md-4 custom-mop-container" style="display: none;">
                                    <label class="form-label fw-bold">Specify Other MOP</label>
                                    <input type="text" name="custom_mop" class="form-control custom-mop-input uppercase-input" placeholder="Enter mode of payment" disabled>
                                </div>

                                <!-- Bank Name Select (Shown for Bank Transfer & Checks) -->
                                <div class="col-md-4 bank-name-container" style="display: none;">
                                    <label class="form-label fw-bold">Bank Name</label>
                                    <select name="bank_name" class="form-select bank-name-select" disabled>
                                        <option value="" selected disabled>-- Select Bank --</option>
                                        <option value="BPI">BPI</option>
                                        <option value="BDO">BDO</option>
                                        <option value="METROBANK">Metrobank</option>
                                        <option value="UNIONBANK">UnionBank</option>
                                        <option value="SECURITY_BANK">Security Bank</option>
                                        <option value="OTHERS">Others</option>
                                    </select>
                                </div>

                                <!-- Custom Bank Input (Shown when Bank is OTHERS) -->
                                <div class="col-md-4 custom-bank-container" style="display: none;">
                                    <label class="form-label fw-bold">Specify Bank Name</label>
                                    <input type="text" name="custom_bank_name" class="form-control custom-bank-input uppercase-input" placeholder="Enter bank name" disabled>
                                </div>

                                <!-- Check Details Fields (Check Number & Check Date) -->
                                <div class="col-md-8 check-details-fields row g-3" style="display: none;">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check Number</label>
                                        <input type="text" name="check_number" class="form-control check-number-input uppercase-input" placeholder="Enter check #">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Check Date</label>
                                        <input type="date" name="check_date" class="form-control">
                                    </div>
                                </div>

                                <div class="col-12 sale-field-heading">Delivery &amp; shipping</div>
                                <!-- Address -->
                                <div class="col-md-8">
                                    <label class="form-label fw-bold">Address / Delivery Location</label>
                                    <input type="text" name="address" class="form-control" placeholder="Complete delivery address">
                                </div>

                                <!-- Delivery Date -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Delivery Date</label>
                                    <input type="date" name="delivery_date" class="form-control">
                                </div>

                                <!-- Shipping Fee Type Options -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Shipping Fee Type</label>
                                    <select id="wholesaleShippingType" name="shipping_fee_type" class="form-select">
                                        <option value="Free">Free</option>
                                        <option value="COD">COD</option>
                                    </select>
                                </div>

                                <!-- Shipping Fee Amount -->
                                <div class="col-md-4" id="wholesaleShippingFeeContainer" style="display: none;">
                                    <label class="form-label fw-bold">Shipping Fee Amount (₱)</label>
                                    <input type="number" step="0.01" id="wholesaleShippingFeeAmount" name="shipping_fee_amount" class="form-control calc-trigger" value="0.00" disabled>
                                </div>

                                <!-- Courier -->
                                <div class="col-md-4" id="wholesaleCourierContainer">
                                    <label class="form-label fw-bold">Courier / Delivered By</label>
                                    <input type="text" id="wholesaleCourier" name="courier" class="form-control uppercase-input" placeholder="e.g. Lalamove, AP Cargo">
                                </div>

                                <div class="col-12 sale-field-heading">Order adjustment &amp; documents</div>
                                <div class="col-md-4 wholesale-adjustment-field">
                                    <div class="wholesale-adjustment-card">
                                        <label class="form-label fw-bold text-danger">Order Discount (%)</label>
                                        <input type="number" step="0.01" min="0" max="100" id="wholesaleAdditionalDiscount" name="additional_discount_percentage" class="form-control calc-trigger" value="0.00">
                                        <div class="form-text">Applies to the whole order. Leave as 0 if there is no discount.</div>
                                    </div>
                                </div>

                                <!-- Payment Proof Container -->
                                <div class="col-md-5 sale-attachment-field payment-proof-container" style="display: none;">
                                    <label class="form-label fw-bold">Payment Proof / Deposit Slip (Image/PDF)</label>
                                    <input type="file" name="proof_of_payment" class="form-control" accept="image/*,application/pdf" disabled>
                                </div>

                                <div class="col-md-8 sale-attachment-field wholesale-order-attachment">
                                    <label class="form-label fw-bold">Order Attachment (Image/PDF)</label>
                                    <input type="file" name="order_slip" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                                    <div class="form-text">Optional. Attach the customer order document for inventory verification (up to 5 MB).</div>
                                </div>

                                <!-- Sub Total & Total Amount Summary Row -->
                                <div class="col-12 sale-field-heading">Order total</div>
                                <div class="col-md-6">
                                    <div class="sale-summary-card">
                                    <label class="form-label fw-bold">Sub Total</label>
                                    <input type="text" id="wholesaleSubTotalDisplay" class="form-control bg-white fw-bold" readonly value="₱0.00">
                                    <input type="hidden" id="wholesaleSubTotal" name="sub_total" value="0.00">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="sale-summary-card sale-summary-card-highlight">
                                    <label class="form-label fw-bold text-success">Total Amount</label>
                                    <input type="text" id="wholesaleGrandTotal" name="grand_total" class="form-control bg-white fw-bold text-success fs-5" readonly value="₱0.00">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EXTRA FIELDS FOR ONLINE ORDERS -->
                    <div id="onlineExtraFields" class="mt-4 p-3 bg-light rounded border extra-fields-container" style="display: none;">
                        <div class="channel-detail-panel">
                        <div class="channel-detail-header">
                            <span class="channel-detail-icon"><i class="fa-solid fa-globe"></i></span>
                            <div><h6 class="channel-detail-title">Online order details</h6><p class="channel-detail-help">Record payment, delivery, and reconciliation information.</p></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-12 sale-field-heading">Payment &amp; delivery status</div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">MOP</label>
                                <select name="online_mop" class="form-select online-input mop-select" disabled>
                                    <option value="">-- Select MOP --</option>
                                    <option value="GCASH">GCASH</option>
                                    <option value="PAYMAYA">PAYMAYA</option>
                                    <option value="BDO">BDO</option>
                                    <option value="BPI">BPI</option>
                                    <option value="DATED_CHECK">Dated Check</option>
                                    <option value="POST_DATED_CHECK">Post-Dated Check</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                                <div class="custom-online-mop-container mt-2" style="display: none;">
                                    <label class="form-label fw-bold">Specify Other MOP</label>
                                    <input type="text" name="custom_mop" class="form-control online-input custom-online-mop-input uppercase-input" placeholder="Enter other payment method" disabled>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Delivery Date</label>
                                <input type="date" name="delivery_date" class="form-control online-input" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Delivery Status</label>
                                <select name="delivery_status" class="form-select online-input" disabled>
                                    <option value="pending">Pending</option>
                                    <option value="preparing">Preparing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-6 online-bank-name-container" style="display: none;">
                                <label class="form-label fw-bold">Bank Name</label>
                                <select name="bank_name" class="form-select online-input online-bank-name-select" disabled>
                                    <option value="" selected disabled>-- Select Bank --</option>
                                    <option value="BPI">BPI</option>
                                    <option value="BDO">BDO</option>
                                    <option value="METROBANK">Metrobank</option>
                                    <option value="UNIONBANK">UnionBank</option>
                                    <option value="SECURITY_BANK">Security Bank</option>
                                    <option value="OTHERS">Others</option>
                                </select>
                            </div>
                            <div class="col-md-6 online-custom-bank-container" style="display: none;">
                                <label class="form-label fw-bold">Specify Bank Name</label>
                                <input type="text" name="custom_bank_name" class="form-control online-input online-custom-bank-input uppercase-input" placeholder="Enter bank name" disabled>
                            </div>
                            <div class="col-md-6 online-check-details-fields" style="display: none;">
                                <label class="form-label fw-bold">Check Number</label>
                                <input type="text" name="check_number" class="form-control online-input check-number-input uppercase-input" placeholder="Enter check number" disabled>
                            </div>
                            <div class="col-md-6 online-check-details-fields" style="display: none;">
                                <label class="form-label fw-bold">Check Date</label>
                                <input type="date" name="check_date" class="form-control online-input" disabled>
                            </div>
                            <div class="col-12 sale-field-heading">Delivery destination</div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Delivery Address</label>
                                <textarea name="address" class="form-control online-input" rows="2" placeholder="Complete delivery address" disabled></textarea>
                            </div>

                            <div class="col-12 sale-field-heading">Order documents</div>
                            <div class="col-md-6 sale-attachment-field">
                                <label class="form-label fw-bold">Order Attachment (Image/PDF)</label>
                                <input type="file" name="order_slip" class="form-control online-input" accept="image/jpeg,image/png,image/webp,application/pdf" disabled>
                                <div class="form-text">Optional. Attach the customer order document for inventory verification (up to 5 MB).</div>
                            </div>
                            <div class="col-md-6 payment-proof-container" style="display: none;">
                                <label class="form-label fw-bold">Proof of Payment</label>
                                <input type="file" name="proof_of_payment" class="form-control online-input" accept="image/jpeg,image/png,image/jpg,application/pdf" disabled>
                                <div class="form-text">JPG, PNG, or PDF up to 2 MB.</div>
                            </div>
                            <div class="col-12 sale-field-heading">Payment reconciliation</div>
                            <div class="col-12">
                                <div class="row g-3 online-reconciliation-grid">
                                    <div class="col-md-3">
                                        <div class="sale-summary-card">
                                            <label class="form-label">Sub Total Amount</label>
                                            <input type="number" step="0.01" name="online_sub_total" id="onlineSubTotal" class="form-control bg-white" readonly placeholder="0.00">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sale-summary-card">
                                            <label class="form-label">Shipping Fee</label>
                                            <input type="number" step="0.01" min="0" name="delivery_fee" id="onlineDeliveryFee" class="form-control online-input calc-trigger" value="0" placeholder="0.00" disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sale-summary-card">
                                            <label class="form-label fw-bold text-success">Proof / Received Amount (Total Amount)</label>
                                            <input type="number" step="0.01" name="proof_amount" id="onlineProofAmount" class="form-control bg-white fw-bold online-input" value="0.00" readonly disabled>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="sale-summary-card">
                                            <label class="form-label">Difference</label>
                                            <input type="number" step="0.01" id="onlineDifference" class="form-control bg-white fw-bold" value="0.00" readonly>
                                        </div>
                                    </div>
                                    <input type="hidden" name="grand_total" id="onlineGrandTotal" value="0.00">
                                </div>
                            </div>
                            <div class="col-12 sale-field-heading">Remarks</div>
                            <div class="col-12">
                                <div class="online-remarks-panel">
                                    <label class="form-label fw-semibold" for="onlineOrderRemarks">Reconciliation note <span class="text-muted fw-normal">(optional)</span></label>
                                    <textarea id="onlineOrderRemarks" name="note" class="form-control online-input" rows="2" maxlength="2000" placeholder="Add a note about payment received, any difference, or follow-up needed." disabled></textarea>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- EXTRA FIELDS FOR WALK-IN SALES -->
                    <div id="walkInExtraFields" class="mt-4 p-3 bg-light rounded border extra-fields-container" style="display: none;">
                        <h6 class="fw-bold text-info mb-3"><i class="fa-solid fa-person-walking me-1"></i> Walk-In Tracking Details</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mode of Payment (MOP)</label>
                                <select name="walkin_mop" class="form-select walk-in-mop-select" disabled>
                                    <option value="">-- Select MOP --</option>
                                    <option value="CASH">CASH</option>
                                    <option value="BANK_TRANSFER">BANK TRANSFER</option>
                                    <option value="GCASH">GCASH</option>
                                    <option value="PAYMAYA">PAYMAYA</option>
                                    <option value="BDO">BDO</option>
                                    <option value="METROBANK">METROBANK</option>
                                    <option value="BPI">BPI</option>
                                    <option value="DATED_CHECK">Dated Check</option>
                                    <option value="POST_DATED_CHECK">Post-Dated Check</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                                <div class="custom-walkin-mop-container mt-2" style="display: none;">
                                    <label class="form-label fw-bold">Specify Other MOP</label>
                                    <input type="text" name="custom_mop" class="form-control walk-in-input custom-walkin-mop-input uppercase-input" placeholder="Enter other payment method" disabled>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Note / Walk-In Remarks</label>
                                <input type="text" name="walkin_remarks" class="form-control walk-in-input" placeholder="Optional notes" disabled>
                            </div>

                            <div class="col-md-6 walkin-check-details-fields" style="display: none;">
                                <label class="form-label fw-bold">Bank Name</label>
                                <select name="bank_name" class="form-select walk-in-input walkin-bank-name-select" disabled>
                                    <option value="">-- Select Bank --</option>
                                    <option value="BPI">BPI</option>
                                    <option value="BDO">BDO</option>
                                    <option value="METROBANK">Metrobank</option>
                                    <option value="UNIONBANK">UnionBank</option>
                                    <option value="SECURITY_BANK">Security Bank</option>
                                    <option value="OTHERS">Others</option>
                                </select>
                            </div>
                            <div class="col-md-6 walkin-custom-bank-container" style="display: none;">
                                <label class="form-label fw-bold">Specify Bank Name</label>
                                <input type="text" name="custom_bank_name" class="form-control walk-in-input walkin-custom-bank-input uppercase-input" disabled>
                            </div>
                            <div class="col-md-6 walkin-check-details-fields" style="display: none;">
                                <label class="form-label fw-bold">Check Number</label>
                                <input type="text" name="check_number" class="form-control walk-in-input check-number-input uppercase-input" disabled>
                            </div>
                            <div class="col-md-6 walkin-check-details-fields" style="display: none;">
                                <label class="form-label fw-bold">Check Date</label>
                                <input type="date" name="check_date" class="form-control walk-in-input" disabled>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold">Proof of Quotation</label>
                                <input type="file" name="quotation_proofs[]" class="form-control walk-in-input" accept="image/jpeg,image/png,image/webp,application/pdf" multiple disabled data-inline-attachment-preview>
                                <div class="form-text">Up to 4 attachments total, 2 MB each.</div>
                                <div class="d-flex flex-wrap gap-2 mt-2" data-inline-attachment-preview-list></div>
                            </div>
                            <div class="col-md-6 walkin-payment-proof-container" style="display: none;">
                                <label class="form-label fw-bold">Proof of Payment</label>
                                <input type="file" name="walkin_payment_proofs[]" class="form-control walk-in-input" accept="image/jpeg,image/png,image/webp,application/pdf" multiple disabled data-inline-attachment-preview>
                                <div class="form-text">Required for all payments except cash. Up to 4 attachments total, 2 MB each.</div>
                                <div class="d-flex flex-wrap gap-2 mt-2" data-inline-attachment-preview-list></div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Sub Total</label>
                                <input type="number" step="0.01" name="walkin_sub_total" id="walkInSubTotal" class="form-control bg-white" readonly placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Additional Discount (%)</label>
                                <input type="number" step="0.01" name="additional_discount" id="walkInAdditionalDiscount" class="form-control calc-trigger" value="0" placeholder="0.00" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-success">Total Amount</label>
                                <input type="number" step="0.01" name="grand_total" id="walkInGrandTotal" class="form-control bg-white fw-bold text-success" readonly placeholder="0.00">
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer bg-light w-100"><span class="small text-muted me-auto">Stock is deducted after inventory verification.</span>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-semibold">Submit for Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

@php
    $saleProductOptions = $products->map(fn ($product) => [
        'id' => $product->id,
        'item_id' => $product->item_id,
        'name' => $product->name,
        'label' => ($product->item_id ?: $product->id).' — '.($product->name ?: $product->description ?: 'Unnamed product'),
        'barcode' => $product->barcode,
        'description' => $product->description ?: $product->name,
        'sales' => $product->sales_price ?? 0,
        'wholesale' => $product->wholesale_price ?? 0,
        'shopee' => $product->shopee_price ?? 0,
        'lazada' => $product->lazada_price ?? 0,
        'tiktok' => $product->sales_price ?? 0,
    ])->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 0;
    const channelSelect = document.getElementById('channelTypeSelect');
    let shippingTypeSelect = document.getElementById('wholesaleShippingType');
    let shippingFeeInput = document.getElementById('wholesaleShippingFeeAmount');
    let shippingContainer = document.getElementById('wholesaleShippingFeeContainer');
    const customerInput = document.getElementById('saleCustomerName');
    const customerOptions = document.getElementById('existingSaleCustomers');
    let customerLookupTimeout;
    let customerLookupSequence = 0;
    const walkInProofInputs = [...document.querySelectorAll('#walkInExtraFields input[type="file"]')];
    walkInProofInputs.forEach(input => input.addEventListener('change', () => {
        const total = walkInProofInputs.reduce((count, field) => count + field.files.length, 0);
        if (total > 4) {
            input.value = '';
            window.AppAlert?.show('Upload no more than 4 attachments in total.', 'error');
        }
    }));
    const productOptions = @json($saleProductOptions);
    const productDataList = document.createElement('datalist');
    productDataList.id = 'saleProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = product.label;
        productDataList.appendChild(option);
    });
    document.body.appendChild(productDataList);
    setupProductSuggestions(document.getElementById('recordSaleModal'), productDataList, productOptions,
        @json(route('hub.products.search.ajax', $hub->id).'?exclude_brand=1'), product => ({
            id: product.id, item_id: product.item_id, name: product.name, label: `${product.item_id || product.id} — ${product.name || product.description || 'Unnamed product'}`,
            barcode: product.barcode, description: product.description || '',
            sales: product.sales_price || 0, wholesale: product.wholesale_price || 0,
            shopee: product.shopee_price || 0, lazada: product.lazada_price || 0, tiktok: product.sales_price || 0,
        }), true, 'item_id');
    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            if (!value) {
                hidden.value = '';
                Object.keys(row.dataset).forEach(key => delete row.dataset[key]);
                updateRowDisplay(row);
                calculateTotals();
                return;
            }
            const product = productOptions.find(item =>
                [
                    item.label,
                    item.item_id,
                    item.barcode,
                    item.name,
                ].some(identifier => String(identifier || '').trim().toLowerCase() === value)
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
            if (product) {
                Object.entries(product).forEach(([key, item]) => row.dataset[key] = item ?? '');
            } else {
                Object.keys(row.dataset).forEach(key => delete row.dataset[key]);
            }
            updateRowDisplay(row);
            calculateTotals();
        });
    };
    bindProductSearch(document.querySelector('.product-row'));
    document.getElementById('recordSaleModal')?.addEventListener('show.bs.modal', () => {
        document.querySelectorAll('#productRowsContainer .product-row').forEach(row => {
            const input = row.querySelector('.product-search');
            const hidden = row.querySelector('.product-id');
            if (input) input.value = '';
            if (hidden) hidden.value = '';
            Object.keys(row.dataset).forEach(key => delete row.dataset[key]);
            updateRowDisplay(row);
        });
        calculateTotals();
    });

    // Initialize Tom Select on the Channel Select dropdown if applicable, or bind events cleanly
    if (channelSelect) {
        channelSelect.addEventListener('change', handleChannelChange);
        const loadCustomerOptions = async (search = '') => {
            customerOptions.replaceChildren();
            if (!channelSelect.value) return;
            const lookupSequence = ++customerLookupSequence;
            try {
                const url = new URL(@json(route('hub.sales.customers', $hub->id)), window.location.origin);
                url.searchParams.set('channel', channelSelect.value);
                if (search.trim()) url.searchParams.set('q', search.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Customer lookup unavailable');
                const customers = await response.json();
                if (lookupSequence !== customerLookupSequence
                    || channelSelect.value !== url.searchParams.get('channel')
                    || customerInput.value.trim() !== search.trim()) return;
                customers.forEach(customer => {
                    const option = document.createElement('option');
                    option.value = customer.name;
                    option.dataset.contactNumber = customer.contact_number || '';
                    customerOptions.appendChild(option);
                });
            } catch (error) {
                window.AppAlert?.show('Existing customers could not be loaded. You can still type a new customer.', 'warning');
            }
        };
        channelSelect.addEventListener('change', () => {
            customerInput.value = '';
            loadCustomerOptions();
        });
        customerInput.addEventListener('input', () => {
            window.clearTimeout(customerLookupTimeout);
            customerLookupTimeout = window.setTimeout(
                () => loadCustomerOptions(customerInput.value),
                250
            );
        });
        customerInput.addEventListener('change', () => {
            const selected = [...customerOptions.options].find(option => option.value === customerInput.value);
        });
        if (channelSelect.value) {
            handleChannelChange();
            loadCustomerOptions(customerInput.value);
        }
    }

    // Global Input Listener for Uppercase and Calculation Triggers
    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('customer-name-input')) {
            const input = e.target;
            const cursorPosition = input.selectionStart;
            const beforeCursor = input.value.slice(0, cursorPosition);

            input.value = input.value
                .toLowerCase()
                .replace(/(^|\s)([a-z])/g, (match, separator, letter) => separator + letter.toUpperCase());

            input.setSelectionRange(beforeCursor.length, beforeCursor.length);
        }
        if (e.target && (e.target.classList.contains('custom-mop-input') || e.target.classList.contains('custom-bank-input') || e.target.classList.contains('custom-online-mop-input') || e.target.classList.contains('custom-walkin-mop-input') || e.target.classList.contains('mode-of-payment-others-input') || e.target.classList.contains('check-number-input') || e.target.id === 'wholesaleCourier')) {
            e.target.value = e.target.value.toUpperCase();
        }
        if (e.target.classList.contains('calc-trigger') || e.target.classList.contains('qty-input') || e.target.classList.contains('item-discount-input') || e.target.id === 'onlineDeliveryFee' || e.target.id === 'wholesaleShippingFeeAmount' || e.target.id === 'wholesaleAdditionalDiscount' || e.target.id === 'wholesaleWithholdingTaxPercent' || e.target.id === 'walkInAdditionalDiscount') {
            calculateTotals();
        }
    });

    // Main Channel Switch Handler
    function handleChannelChange() {
        const selectEl = document.getElementById('channelTypeSelect');
        const rawVal = selectEl ? (selectEl.value || '').trim() : '';
        const platformOrderNumberField = document.getElementById('platformOrderNumberField');
        const automaticOrderNumberField = document.getElementById('automaticOrderNumberField');
        const platformOrderNumberInput = platformOrderNumberField?.querySelector('input[name="order_number"]');
        const platformOrderNumberLabel = document.getElementById('platformOrderNumberLabel');
        const platformOrderNumberHelp = document.getElementById('platformOrderNumberHelp');
        const usesPlatformOrderNumber = ['tiktok', 'shopee', 'lazada'].includes(rawVal);
        if (platformOrderNumberField && automaticOrderNumberField && platformOrderNumberInput) {
            platformOrderNumberField.style.display = usesPlatformOrderNumber ? '' : 'none';
            automaticOrderNumberField.style.display = usesPlatformOrderNumber ? 'none' : '';
            platformOrderNumberInput.disabled = !usesPlatformOrderNumber;
            platformOrderNumberInput.required = rawVal === 'tiktok';
            const isMarketplaceInvoice = ['shopee', 'lazada'].includes(rawVal);
            platformOrderNumberInput.placeholder = isMarketplaceInvoice ? 'Enter invoice number (optional)' : 'Enter the platform order number';
            if (platformOrderNumberLabel) platformOrderNumberLabel.textContent = isMarketplaceInvoice ? 'Invoice No.' : 'Platform Order Number';
            if (platformOrderNumberHelp) platformOrderNumberHelp.textContent = isMarketplaceInvoice ? 'Leave blank to generate an invoice number automatically.' : '';
        }
        const marketplaceMop = document.querySelector('#marketplaceExtraFields .mop-select');
        const marketplaceMopButton = document.getElementById('marketplaceMopButton');
        if (marketplaceMop && ['shopee', 'lazada'].includes(rawVal)) {
            marketplaceMop.querySelectorAll('option[data-market-channel]').forEach(option => {
                const available = option.dataset.marketChannel === rawVal;
                option.hidden = !available;
                option.disabled = !available;
                if (!available && option.selected) marketplaceMop.value = '';
            });
            document.querySelectorAll('.marketplace-mop-option[data-market-channel]').forEach(option => {
                option.hidden = option.dataset.marketChannel !== rawVal;
            });
            if (marketplaceMopButton) marketplaceMopButton.textContent = marketplaceMop.selectedOptions[0]?.textContent || '-- Select MOP --';
        }
        const automaticOrderNumberValue = document.getElementById('automaticOrderNumberValue');
        if (automaticOrderNumberValue) {
            if (usesPlatformOrderNumber || !['walk_in', 'online', 'wholesale'].includes(rawVal)) {
                automaticOrderNumberValue.textContent = 'Select a sales channel';
            } else {
                automaticOrderNumberValue.textContent = 'Loading order number...';
                fetch('{{ route('sales.order-number-preview') }}?channel=' + encodeURIComponent(rawVal), {
                    headers: { 'Accept': 'application/json' },
                })
                    .then(response => {
                        if (!response.ok) throw new Error('Unable to load order number');
                        return response.json();
                    })
                    .then(data => {
                        automaticOrderNumberValue.textContent = data.order_number;
                    })
                    .catch(() => {
                        automaticOrderNumberValue.textContent = 'Order number unavailable';
                    });
            }
        }

        // Hide and disable all extra field containers first
        const allContainerIds = [
            'marketplaceExtraFields', 
            'wholesaleExtraFields', 
            'onlineExtraFields', 
            'walkInExtraFields'
        ];

        allContainerIds.forEach(id => {
            const container = document.getElementById(id);
            if (container) {
                container.style.display = 'none';
                container.querySelectorAll('input, select, textarea').forEach(field => {
                    field.disabled = true;
                });
            }
        });

        // Map exact option values to their containers
        let activeContainerId = '';
        if (rawVal === 'shopee' || rawVal === 'lazada') {
            activeContainerId = 'marketplaceExtraFields';
        } else if (rawVal === 'wholesale') {
            activeContainerId = 'wholesaleExtraFields';
        } else if (rawVal === 'online') {
            activeContainerId = 'onlineExtraFields';
        } else if (rawVal === 'walk_in') {
            activeContainerId = 'walkInExtraFields';
        }

        // Show and enable matching active container
        if (activeContainerId) {
            const activeContainer = document.getElementById(activeContainerId);
            if (activeContainer) {
                activeContainer.style.display = 'block';
                activeContainer.querySelectorAll('input, select, textarea').forEach(el => {
                    if (el.id === 'onlineSubTotal' || el.id === 'walkInSubTotal' || el.id === 'walkInGrandTotal' || el.id === 'shopeeSubTotal' || el.id === 'wholesaleSubTotal' || el.id === 'wholesaleGrandTotal') {
                        el.disabled = true;
                    } else {
                        el.disabled = false;
                    }
                });
                
                // Re-evaluate conditional fields if wholesale is shown
                if (activeContainerId === 'wholesaleExtraFields') {
                    const mopSelect = activeContainer.querySelector('.wholesale-mop-select');
                    if (mopSelect) evaluateWholesaleMop(mopSelect);
                    
                    const shipSelect = document.getElementById('wholesaleShippingType');
                    if (shipSelect) updateWholesaleShippingState(shipSelect.value);
                }
                if (activeContainerId === 'walkInExtraFields') {
                    const mopSelect = activeContainer.querySelector('.walk-in-mop-select');
                    if (mopSelect) evaluateWalkInMop(mopSelect);
                }
                if (activeContainerId === 'onlineExtraFields') {
                    const mopSelect = activeContainer.querySelector('select[name="online_mop"]');
                    if (mopSelect) evaluateOnlineMop(mopSelect);
                }
                if (activeContainerId === 'marketplaceExtraFields') {
                    activeContainer.querySelector('.mop-select')?.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        }

        // Update product pricing context and recalculate totals based on channel
        document.querySelectorAll('.product-row').forEach(row => {
            updateRowDisplay(row);
        });

        calculateTotals();
    }

    // Add Row functionality
    const addItemBtn = document.getElementById('addItemRowBtn');
    if (addItemBtn) {
        addItemBtn.addEventListener('click', function() {
            rowIndex++;
            const container = document.getElementById('productRowsContainer');
            const channelVal = channelSelect ? channelSelect.value : '';
            const isTikTok = channelVal === 'tiktok';

            const newRow = document.createElement('div');
            newRow.className = 'row g-2 align-items-end product-row mb-3 pb-3 border-bottom';
            newRow.setAttribute('data-index', rowIndex);

            newRow.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label fw-bold d-md-none">Product Description</label>
                    <input type="search" class="form-control product-search" list="saleProductOptions" placeholder="Search item code, barcode, or description" autocomplete="off" required>
                    <input type="hidden" name="items[${rowIndex}][product_id]" class="product-id">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold d-md-none">Quantity</label>
                    <input type="number" name="items[${rowIndex}][quantity]" class="form-control qty-input calc-trigger" min="1" value="1" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold d-md-none">Regular Price</label>
                    <input type="text" class="form-control item-price-display bg-white fw-bold text-primary" readonly value="₱0.00">
                    <input type="hidden" name="items[${rowIndex}][unit_price]" class="item-unit-price-input" value="0">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold text-danger d-md-none">Discount %</label>
                    <input type="number" step="0.01" name="items[${rowIndex}][discount_percentage]" class="form-control item-discount-input calc-trigger" value="0.00" placeholder="0.00">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-success d-md-none">Total after Disc.</label>
                    <input type="text" class="form-control item-total-display bg-white fw-bold text-success" readonly value="₱0.00">
                </div>
                <div class="col-md-2 tiktok-shipping-fee-column">
                    <label class="form-label fw-bold text-info d-md-none">Shipping Service Fee</label>
                    <input type="text" class="form-control item-shipping-service-fee-display bg-white fw-bold text-info" readonly value="₱0.00">
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold d-md-none">Action</label>
                    <button type="button" class="btn btn-outline-danger w-100 remove-row-btn" title="Delete Item">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            `;

            container.appendChild(newRow);
            container.scrollTop = container.scrollHeight;
            bindProductSearch(newRow);
            newRow.querySelector('.product-search').focus();
            updateRowDisplay(newRow);
            calculateTotals();
        });
    }

    // Remove Row functionality via Event Delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-row-btn')) {
            const row = e.target.closest('.product-row');
            const allRows = document.querySelectorAll('.product-row');
            if (allRows.length > 1) {
                row.remove();
                calculateTotals();
            } else {
                AppAlert.show('You must keep at least one product item.');
            }
        }
    });

    // Update individual row prices based on the selected channel pricing attributes
    function updateRowDisplay(row) {
        if (!row) return;
        const priceDisplay = row.querySelector('.item-price-display');
        if (!row.dataset.id) {
            if (priceDisplay) priceDisplay.value = '₱0.00';
            row.setAttribute('data-unit-price', '0');
            const unitPriceInput = row.querySelector('.item-unit-price-input');
            const totalDisplay = row.querySelector('.item-total-display');
            if (unitPriceInput) unitPriceInput.value = '0.00';
            if (totalDisplay) totalDisplay.value = '₱0.00';
            return;
        }

        const channelVal = channelSelect ? (channelSelect.value || '').trim() : '';
        let price = 0;

        // Map pricing columns dynamically based on channel
        if (channelVal === 'shopee') {
            price = parseFloat(row.dataset.shopee) || 0;
        } else if (channelVal === 'lazada') {
            price = parseFloat(row.dataset.lazada) || 0;
        } else if (channelVal === 'tiktok') {
            price = parseFloat(row.dataset.tiktok) || 0;
        } else if (channelVal === 'wholesale') {
            price = parseFloat(row.dataset.wholesale) || 0;
        } else {
            price = parseFloat(row.dataset.sales) || 0;
        }

        if (price === 0) {
            price = parseFloat(row.dataset.sales) || 0;
        }

        row.setAttribute('data-unit-price', price);
        if (priceDisplay) {
            priceDisplay.value = '₱' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const unitPriceInput = row.querySelector('.item-unit-price-input');
        if (unitPriceInput) unitPriceInput.value = price.toFixed(2);
    }

    // Comprehensive Calculations for Subtotals and Total Amounts
    function calculateTotals() {
        let cumulativeSubTotal = 0;
        const channelVal = channelSelect ? (channelSelect.value || '').trim() : '';
        const showTikTokFee = channelVal === 'tiktok';
        document.querySelectorAll('.tiktok-shipping-fee-column').forEach(column => {
            column.style.display = showTikTokFee ? '' : 'none';
        });

        document.querySelectorAll('.product-row').forEach(row => {
            const unitPrice = parseFloat(row.getAttribute('data-unit-price')) || 0;
            const qtyInput = row.querySelector('.qty-input');
            const discountInput = row.querySelector('.item-discount-input');
            const totalDisplay = row.querySelector('.item-total-display');
            const feeDisplay = row.querySelector('.item-shipping-service-fee-display');

            const qty = qtyInput ? parseFloat(qtyInput.value) || 0 : 0;
            const discountPercent = discountInput ? parseFloat(discountInput.value) || 0 : 0;
            const baseAmount = unitPrice * qty;
            const discountAmount = baseAmount * (discountPercent / 100);
            const rowTotal = baseAmount - discountAmount;
            cumulativeSubTotal += rowTotal;

            if (totalDisplay) {
                totalDisplay.value = '₱' + rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            if (feeDisplay) {
                const fee = showTikTokFee ? Number((rowTotal * 0.05).toFixed(2)) : 0;
                feeDisplay.value = '₱' + fee.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });

        // Assign to respective active channel total fields
        if (channelVal === 'shopee' || channelVal === 'lazada') {
            const subTotalInput = document.getElementById('shopeeSubTotal');
            const grandTotalInput = document.getElementById('shopeeGrandTotal');
            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = '₱' + cumulativeSubTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (channelVal === 'wholesale') {
            const subTotalInput = document.getElementById('wholesaleSubTotal');
            const subTotalDisplay = document.getElementById('wholesaleSubTotalDisplay');
            const grandTotalInput = document.getElementById('wholesaleGrandTotal');
            const addDiscInput = document.getElementById('wholesaleAdditionalDiscount');
            const shipTypeSelect = document.getElementById('wholesaleShippingType');
            const shipFeeInput = document.getElementById('wholesaleShippingFeeAmount');
            
            const addDiscPercent = addDiscInput ? parseFloat(addDiscInput.value) || 0 : 0;
            
            const shipType = shipTypeSelect ? shipTypeSelect.value : 'Free';
            const discountedSubTotal = cumulativeSubTotal * (1 - (addDiscPercent / 100));
            const wholesaleGrandTotal = discountedSubTotal;

            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (subTotalDisplay) subTotalDisplay.value = '₱' + cumulativeSubTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            if (grandTotalInput) {
                grandTotalInput.value = '₱' + wholesaleGrandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        } else if (channelVal === 'online') {
            const subTotalInput = document.getElementById('onlineSubTotal');
            const grandTotalInput = document.getElementById('onlineGrandTotal');
            const deliveryFeeInput = document.getElementById('onlineDeliveryFee');
            const proofAmountInput = document.getElementById('onlineProofAmount');
            const differenceInput = document.getElementById('onlineDifference');

            const deliveryFee = deliveryFeeInput ? parseFloat(deliveryFeeInput.value) || 0 : 0;
            const onlineGrandTotal = cumulativeSubTotal + deliveryFee;
            const difference = onlineGrandTotal - cumulativeSubTotal;

            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = onlineGrandTotal.toFixed(2);
            if (proofAmountInput) proofAmountInput.value = onlineGrandTotal.toFixed(2);
            if (differenceInput) differenceInput.value = difference.toFixed(2);
        } else if (channelVal === 'walk_in') {
            const subTotalInput = document.getElementById('walkInSubTotal');
            const grandTotalInput = document.getElementById('walkInGrandTotal');
            const addDiscInput = document.getElementById('walkInAdditionalDiscount');

            const addDiscPercent = addDiscInput ? parseFloat(addDiscInput.value) || 0 : 0;
            const walkInGrandTotal = cumulativeSubTotal * (1 - (addDiscPercent / 100));

            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = walkInGrandTotal.toFixed(2);
        }
    }

    // Wholesale MOP and Shipping Evaluators
    function evaluateWholesaleMop(selectElement) {
        const mopVal = selectElement.value;
        const extraContainer = document.getElementById('wholesaleExtraFields');
        if (!extraContainer) return;

        const bankContainer = extraContainer.querySelector('.bank-name-container');
        const bankSelect = extraContainer.querySelector('.bank-name-select');
        const customBankContainer = extraContainer.querySelector('.custom-bank-container');
        const customBankInput = extraContainer.querySelector('.custom-bank-input');
        const checkFields = extraContainer.querySelector('.check-details-fields');
        const customMopContainer = extraContainer.querySelector('.custom-mop-container');
        const customMopInput = extraContainer.querySelector('.custom-mop-input');
        const proofContainer = extraContainer.querySelector('.payment-proof-container');
        const proofInput = proofContainer ? proofContainer.querySelector('input') : null;
        const adjustmentField = extraContainer.querySelector('.wholesale-adjustment-field');
        const orderAttachment = extraContainer.querySelector('.wholesale-order-attachment');
        const reflowDocuments = () => {
            const proofVisible = proofContainer && proofContainer.style.display !== 'none';
            adjustmentField?.classList.toggle('col-md-4', !proofVisible);
            adjustmentField?.classList.toggle('col-md-3', proofVisible);
            orderAttachment?.classList.toggle('col-md-8', !proofVisible);
            orderAttachment?.classList.toggle('col-md-4', proofVisible);
        };

        if (mopVal === 'CASH') {
            if (bankContainer) bankContainer.style.display = 'none';
            if (bankSelect) bankSelect.disabled = true;
            if (customBankContainer) customBankContainer.style.display = 'none';
            if (customBankInput) { customBankInput.disabled = true; customBankInput.required = false; }
            if (checkFields) {
                checkFields.style.display = 'none';
                checkFields.querySelectorAll('input').forEach(inp => { inp.disabled = true; inp.required = false; });
            }
            if (customMopContainer) customMopContainer.style.display = 'none';
            if (customMopInput) { customMopInput.disabled = true; customMopInput.required = false; }
            if (proofContainer) proofContainer.style.display = 'none';
            if (proofInput) { proofInput.value = ''; proofInput.disabled = true; proofInput.required = false; }
        } else if (mopVal === 'BANK_TRANSFER') {
            if (bankContainer) bankContainer.style.display = 'block';
            if (bankSelect) bankSelect.disabled = false;
            if (customBankContainer) {
                if (bankSelect.value === 'OTHERS') {
                    customBankContainer.style.display = 'block';
                    if (customBankInput) { customBankInput.disabled = false; customBankInput.required = true; }
                } else {
                    customBankContainer.style.display = 'none';
                    if (customBankInput) { customBankInput.disabled = true; customBankInput.required = false; }
                }
            }
            if (checkFields) {
                checkFields.style.display = 'none';
                checkFields.querySelectorAll('input').forEach(inp => { inp.disabled = true; inp.required = false; });
            }
            if (customMopContainer) customMopContainer.style.display = 'none';
            if (customMopInput) { customMopInput.disabled = true; customMopInput.required = false; }
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) { proofInput.disabled = false; proofInput.required = true; }
        } else if (mopVal === 'DATED_CHECK' || mopVal === 'POST_DATED_CHECK') {
            if (bankContainer) bankContainer.style.display = 'block';
            if (bankSelect) bankSelect.disabled = false;
            if (customBankContainer) {
                if (bankSelect.value === 'OTHERS') {
                    customBankContainer.style.display = 'block';
                    if (customBankInput) { customBankInput.disabled = false; customBankInput.required = true; }
                } else {
                    customBankContainer.style.display = 'none';
                    if (customBankInput) { customBankInput.disabled = true; customBankInput.required = false; }
                }
            }
            if (checkFields) {
                checkFields.style.display = 'flex';
                checkFields.querySelectorAll('input').forEach(inp => { inp.disabled = false; inp.required = true; });
            }
            if (customMopContainer) customMopContainer.style.display = 'none';
            if (customMopInput) { customMopInput.disabled = true; customMopInput.required = false; }
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) { proofInput.disabled = false; proofInput.required = true; }
        } else if (mopVal === 'OTHERS') {
            if (bankContainer) bankContainer.style.display = 'none';
            if (bankSelect) bankSelect.disabled = true;
            if (customBankContainer) customBankContainer.style.display = 'none';
            if (customBankInput) { customBankInput.disabled = true; customBankInput.required = false; }
            if (checkFields) {
                checkFields.style.display = 'none';
                checkFields.querySelectorAll('input').forEach(inp => { inp.disabled = true; inp.required = false; });
            }
            if (customMopContainer) customMopContainer.style.display = 'block';
            if (customMopInput) { customMopInput.disabled = false; customMopInput.required = true; }
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) { proofInput.disabled = false; proofInput.required = true; }
        }
        reflowDocuments();
    }

    function evaluateOnlineMop(selectElement) {
        const mopVal = selectElement.value;
        const extraContainer = document.getElementById('onlineExtraFields');
        if (!extraContainer) return;

        const customMopContainer = extraContainer.querySelector('.custom-online-mop-container');
        const customMopInput = extraContainer.querySelector('.custom-online-mop-input');
        const bankContainer = extraContainer.querySelector('.online-bank-name-container');
        const bankSelect = extraContainer.querySelector('.online-bank-name-select');
        const customBankContainer = extraContainer.querySelector('.online-custom-bank-container');
        const customBankInput = extraContainer.querySelector('.online-custom-bank-input');
        const checkFields = extraContainer.querySelectorAll('.online-check-details-fields');
        const checkInputs = extraContainer.querySelectorAll('.online-check-details-fields input');
        const proofContainer = extraContainer.querySelector('.payment-proof-container');
        const proofInput = proofContainer ? proofContainer.querySelector('input') : null;

        if (mopVal === 'OTHERS') {
            if (customMopContainer) customMopContainer.style.display = 'block';
            if (customMopInput) {
                customMopInput.disabled = false;
                customMopInput.required = true;
            }
        } else {
            if (customMopContainer) customMopContainer.style.display = 'none';
            if (customMopInput) {
                customMopInput.value = '';
                customMopInput.disabled = true;
                customMopInput.required = false;
            }
        }

        const isCheckPayment = ['DATED_CHECK', 'POST_DATED_CHECK'].includes(mopVal);
        if (bankContainer) bankContainer.style.display = isCheckPayment ? 'block' : 'none';
        if (!isCheckPayment && bankSelect) {
            bankSelect.value = '';
            bankSelect.disabled = true;
            bankSelect.required = false;
        } else if (bankSelect) {
            bankSelect.disabled = false;
            bankSelect.required = true;
        }
        if (!isCheckPayment && customBankContainer) customBankContainer.style.display = 'none';
        if (!isCheckPayment && customBankInput) {
            customBankInput.value = '';
            customBankInput.disabled = true;
            customBankInput.required = false;
        }
        checkFields.forEach((field) => {
            field.style.display = isCheckPayment ? 'block' : 'none';
        });
        checkInputs.forEach((input) => {
            input.disabled = !isCheckPayment;
            input.required = isCheckPayment;
            if (!isCheckPayment) input.value = '';
        });

        if (mopVal && mopVal !== '') {
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) { proofInput.disabled = false; proofInput.required = true; }
        } else {
            if (proofContainer) proofContainer.style.display = 'none';
            if (proofInput) {
                proofInput.value = '';
                proofInput.disabled = true;
                proofInput.required = false;
            }
        }
    }

    function evaluateWalkInMop(selectElement) {
        const mopVal = selectElement.value;
        const extraContainer = document.getElementById('walkInExtraFields');
        if (!extraContainer) return;

        const customMopContainer = extraContainer.querySelector('.custom-walkin-mop-container');
        const customMopInput = extraContainer.querySelector('.custom-walkin-mop-input');
        const isCheckPayment = ['DATED_CHECK', 'POST_DATED_CHECK'].includes(mopVal);
        const bankSelect = extraContainer.querySelector('.walkin-bank-name-select');
        const customBankContainer = extraContainer.querySelector('.walkin-custom-bank-container');
        const customBankInput = extraContainer.querySelector('.walkin-custom-bank-input');
        const checkFields = extraContainer.querySelectorAll('.walkin-check-details-fields');
        const proofContainer = extraContainer.querySelector('.walkin-payment-proof-container');
        const proofInput = proofContainer ? proofContainer.querySelector('input') : null;

        if (mopVal === 'OTHERS') {
            if (customMopContainer) customMopContainer.style.display = 'block';
            if (customMopInput) {
                customMopInput.disabled = false;
                customMopInput.required = true;
            }
        } else {
            if (customMopContainer) customMopContainer.style.display = 'none';
            if (customMopInput) {
                customMopInput.value = '';
                customMopInput.disabled = true;
                customMopInput.required = false;
            }
        }

        if (mopVal && mopVal !== 'CASH') {
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) { proofInput.disabled = false; proofInput.required = true; }
        } else {
            if (proofContainer) proofContainer.style.display = 'none';
            if (proofInput) {
                proofInput.value = '';
                proofInput.disabled = true;
                proofInput.required = false;
            }
        }
        checkFields.forEach(field => field.style.display = isCheckPayment ? 'block' : 'none');
        checkFields.forEach(field => field.querySelectorAll('input, select').forEach(input => {
            input.disabled = !isCheckPayment;
            input.required = isCheckPayment;
            if (!isCheckPayment) input.value = '';
        }));
        const otherBank = isCheckPayment && bankSelect?.value === 'OTHERS';
        if (customBankContainer) customBankContainer.style.display = otherBank ? 'block' : 'none';
        if (customBankInput) {
            customBankInput.disabled = !otherBank;
            customBankInput.required = otherBank;
            if (!otherBank) customBankInput.value = '';
        }
    }

    document.addEventListener('click', function(e) {
        const option = e.target.closest('.marketplace-mop-option');
        if (!option) return;
        const select = document.getElementById('mopSelect');
        const button = document.getElementById('marketplaceMopButton');
        if (!select || select.disabled || option.hidden) return;
        select.value = option.dataset.value || '';
        if (button) button.textContent = option.textContent.trim();
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });

    // Event delegation for dynamic changes on sub-dropdowns
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('mop-select') && (e.target.closest('#marketplaceExtraFields') || e.target.classList.contains('marketplace-mop-container'))) {
            const mopVal = e.target.value;
            const parentCol = e.target.closest('.col-md-4');
            const othersInput = parentCol ? parentCol.querySelector('#mopOthersInput') : document.getElementById('mopOthersInput');

            if (mopVal === 'OTHERS') {
                if (othersInput) {
                    othersInput.style.display = 'block';
                    othersInput.disabled = false;
                    othersInput.required = true;
                }
            } else {
                if (othersInput) {
                    othersInput.style.display = 'none';
                    othersInput.value = '';
                    othersInput.disabled = true;
                    othersInput.required = false;
                }
            }
        }

        if (e.target && e.target.classList.contains('mop-select') && e.target.closest('#onlineExtraFields')) {
            evaluateOnlineMop(e.target);
        }

        if (e.target && e.target.classList.contains('walk-in-mop-select')) {
            evaluateWalkInMop(e.target);
        }

        if (e.target && e.target.classList.contains('wholesale-mop-select')) {
            evaluateWholesaleMop(e.target);
        }

        if (e.target && (e.target.classList.contains('bank-name-select') || e.target.classList.contains('online-bank-name-select'))) {
            const extraContainer = e.target.closest('#wholesaleExtraFields, #onlineExtraFields');
            if (extraContainer) {
                const customBankContainer = extraContainer.querySelector('.custom-bank-container, .online-custom-bank-container');
                const customBankInput = extraContainer.querySelector('.custom-bank-input, .online-custom-bank-input');
                
                if (e.target.value === 'OTHERS') {
                    if (customBankContainer) customBankContainer.style.display = 'block';
                    if (customBankInput) {
                        customBankInput.disabled = false;
                        customBankInput.required = true;
                    }
                } else {
                    if (customBankContainer) customBankContainer.style.display = 'none';
                    if (customBankInput) {
                        customBankInput.value = '';
                        customBankInput.disabled = true;
                        customBankInput.required = false;
                    }
                }
            }
        }
        if (e.target && e.target.classList.contains('walkin-bank-name-select')) {
            evaluateWalkInMop(e.target.closest('#walkInExtraFields').querySelector('.walk-in-mop-select'));
        }

    });

    function updateWholesaleShippingState(val) {
        const courierContainer = document.getElementById('wholesaleCourierContainer');
        if (val === 'COD') {
            if (shippingContainer) shippingContainer.style.display = 'block';
            courierContainer?.classList.remove('col-md-8');
            courierContainer?.classList.add('col-md-4');
            if (shippingFeeInput) {
                shippingFeeInput.disabled = false;
                shippingFeeInput.type = 'number';
                shippingFeeInput.placeholder = '0.00';
                if (shippingFeeInput.value === '0.00' || !shippingFeeInput.value) shippingFeeInput.value = '0.00';
            }
        } else {
            if (shippingContainer) shippingContainer.style.display = 'none';
            courierContainer?.classList.remove('col-md-4');
            courierContainer?.classList.add('col-md-8');
            if (shippingFeeInput) {
                shippingFeeInput.value = '0.00';
                shippingFeeInput.disabled = true;
            }
        }
    }

    if (shippingTypeSelect) {
        shippingTypeSelect.addEventListener('change', function () {
            updateWholesaleShippingState(this.value);
            calculateTotals();
        });
    }
});
    document.querySelectorAll('[data-inline-attachment-preview]').forEach(input => {
        const previewList = input.parentElement.querySelector('[data-inline-attachment-preview-list]');
        let previewUrls = [];
        if (!previewList) return;

        const clearPreviews = () => {
            previewUrls.forEach(url => URL.revokeObjectURL(url));
            previewUrls = [];
            previewList.replaceChildren();
        };

        input.addEventListener('change', () => {
            clearPreviews();
            Array.from(input.files || []).forEach(file => {
                const url = URL.createObjectURL(file);
                previewUrls.push(url);
                const preview = document.createElement(file.type === 'application/pdf' ? 'a' : 'img');
                if (preview instanceof HTMLImageElement) {
                    preview.src = url;
                    preview.alt = `Preview of ${file.name}`;
                    preview.className = 'border rounded';
                    preview.style.cssText = 'width:96px;height:96px;object-fit:cover';
                } else {
                    preview.href = url;
                    preview.target = '_blank';
                    preview.rel = 'noopener';
                    preview.textContent = `Preview PDF: ${file.name}`;
                    preview.className = 'small border rounded p-2 align-self-start';
                }
                previewList.append(preview);
            });
        });

        input.form?.addEventListener('reset', () => window.setTimeout(clearPreviews));
    });
</script>
