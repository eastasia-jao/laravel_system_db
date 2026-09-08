<style>
    #recordSaleModal .modal-dialog { max-width: 980px; }
    #recordSaleModal .modal-content { border: 0; border-radius: .85rem; }
    #recordSaleForm { display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
    #recordSaleModal .modal-header, #recordSaleModal .modal-footer { padding: .85rem 1.1rem; }
    #recordSaleModal .modal-header { background: #fff; border-bottom: 1px solid #e5e7eb; }
    #recordSaleModal .modal-title { font-size: 1.05rem; color: #253247; }
    #recordSaleModal .modal-body { padding: 1.1rem; }
    #recordSaleModal .form-label { font-size: .8rem; margin-bottom: .3rem; font-weight: 500 !important; }
    #recordSaleModal .form-control, #recordSaleModal .form-select { font-size: .85rem; min-height: 36px; border-color: #dce1e7; border-radius: .4rem; }
    #recordSaleModal h6 { font-size: .875rem; color: #374151 !important; }
    #recordSaleModal hr { margin: 1rem 0 !important; color: #dce1e7; opacity: 1; }
    #recordSaleModal .extra-fields-container { padding: 1rem; margin-top: 1rem !important; background: #f8fafb !important; border: 1px solid #e5e7eb; border-radius: .6rem !important; }
    #recordSaleModal .extra-fields-container .shadow-sm { box-shadow: none !important; }
    #productRowsContainer { max-height: 230px; overflow-y: auto; overflow-x: hidden; scrollbar-gutter: stable; padding: 0 .75rem .5rem; border: 1px solid #e5e7eb; border-radius: .6rem; background: #fff; }
    #productRowsContainer > .row:first-child { position: sticky; top: 0; z-index: 2; background: #f8fafb; padding-top: .65rem; padding-bottom: .65rem; font-size: .75rem; }
    #productRowsContainer .product-row { margin-bottom: .5rem !important; padding-bottom: .5rem !important; }
    #productRowsContainer .product-row:last-child { border-bottom: 0 !important; }
    #productRowsContainer .item-price-display { color: #475569 !important; font-weight: 500 !important; }
    @media (max-width: 767.98px) {
        #productRowsContainer { max-height: 300px; padding-top: .75rem; }
        #recordSaleModal .modal-footer { gap: .5rem; }
    }
</style>

<div class="modal fade" id="recordSaleModal" tabindex="-1" aria-labelledby="recordSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <form action="{{ route('sales.storeMultiChannelSale') }}" method="POST" enctype="multipart/form-data" id="recordSaleForm">
                @csrf
                <input type="hidden" name="store_hub_id" value="{{ $hub->id }}">
                
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="recordSaleModalLabel">
                        <i class="fa-solid fa-cart-shopping me-2"></i> Record Multi-Channel Sale
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-2">
                        <!-- Sales Channel -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Sales Channel</label>
                            <select name="channel_type" id="channelTypeSelect" class="form-select" required onchange="handleChannelChange()">
                                <option value="">-- Select Channel --</option>
                                @foreach(['shopee' => 'Shopee Sales', 'lazada' => 'Lazada Sales', 'tiktok' => 'TikTok Sales', 'wholesale' => 'Wholesale Sales', 'online' => 'Online Orders'] as $channelValue => $channelLabel)
                                    @if(auth()->user()?->role !== 'sales_marketing_staff' || auth()->user()->hasSalesChannel($channelValue))
                                        <option value="{{ $channelValue }}">{{ $channelLabel }}</option>
                                    @endif
                                @endforeach
                                @if(auth()->user()?->role !== 'sales_marketing_staff')
                                    <option value="walk_in">Walk-In Sales</option>
                                @endif
                            </select>
                        </div>

                        <!-- Date -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <!-- Order Number / Invoice -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Order Number / Invoice No.</label>
                            <input type="text" name="order_number" class="form-control" placeholder="Enter invoice or order #" required>
                        </div>

                        <!-- Customer Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Customer Name</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Enter customer full name" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" placeholder="e.g. 09123456789">
                            </div>
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
                            <div class="col-md-2 text-danger tiktok-shipping-col" style="display: none;">Ship. Fee (5%)</div>
                            <div class="col-md-2 text-success">Total after Disc.</div>
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
                            </div>

                            <!-- Discount % -->
                            <div class="col-md-1">
                                <label class="form-label fw-bold text-danger d-md-none">Discount %</label>
                                <input type="number" step="0.01" name="items[0][discount_percentage]" class="form-control item-discount-input calc-trigger" value="0.00" placeholder="0.00">
                            </div>

                            <!-- Shipping Service Fee (5%) -->
                            <div class="col-md-2 tiktok-shipping-col" style="display: none;">
                                <label class="form-label fw-bold text-danger d-md-none" style="font-size: 11px;">Ship. Fee (5%)</label>
                                <input type="number" step="0.01" name="items[0][shipping_service_fee]" class="form-control item-shipping-fee-input bg-white text-danger fw-bold" value="0.00" readonly>
                            </div>

                            <!-- Total after Disc. -->
                            <div class="col-md-2">
                                <label class="form-label fw-bold text-success d-md-none">Total after Disc.</label>
                                <input type="text" class="form-control item-total-display bg-white fw-bold text-success" readonly value="₱0.00">
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
                                        <select name="mode_of_payment" class="form-select bg-white market-input mop-select" id="mopSelect" disabled>
                                            <option value="">-- Select MOP --</option>
                                            <option value="COD">COD</option>
                                            <option value="GCASH">GCASH</option>
                                            <option value="PAYMAYA">PAYMAYA</option>
                                            <option value="BDO">BDO</option>
                                            <option value="BPI">BPI</option>
                                            <option value="OTHERS">OTHERS</option>
                                        </select>
                                        <input type="text" name="mode_of_payment_others" id="mopOthersInput" class="form-control bg-white mt-2 market-input" placeholder="Specify other MOP" style="display: none;" disabled>
                                    </div>

                                    <!-- Proof of Payment -->
                                    <div class="col-md-12 payment-proof-container" style="display: none;">
                                        <label class="form-label fw-bold text-danger">Upload Proof of Payment / Check Image</label>
                                        <input type="file" name="proof_of_payment" class="form-control bg-white" accept="image/*" disabled>
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

                    <!-- EXTRA FIELDS FOR TIKTOK SALES ONLY -->
                    <div id="tiktokExtraFields" class="mt-4 p-3 bg-light rounded border extra-fields-container" style="display: none;">
                        <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-video me-1"></i> TikTok Tracking Details</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Sub Total</label>
                                <input type="number" step="0.01" name="tiktok_sub_total" id="tiktokSubTotal" class="form-control bg-white" readonly placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-dark">Sales after Transaction Fee</label>
                                <input type="number" step="0.01" name="sales_after_transaction_fee" id="tiktokSalesAfterFee" class="form-control tiktok-input" placeholder="0.00" disabled>
                            </div>
                        </div>
                    </div>

                    <!-- Wholesale Extra Fields Container -->
                    <div id="wholesaleExtraFields" class="extra-fields-container" style="display: none;">
                        <div class="border p-3 rounded bg-light mb-3">
                            <h6 class="text-primary fw-bold mb-3"><i class="fa-solid fa-handshake"></i> Wholesale Tracking Details</h6>
                            
                            <div class="row g-2">
                                <!-- Mode of Payment -->
                                <div class="col-md-4">
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
                                        <option value="Custom Amount">Custom Amount</option>
                                    </select>
                                </div>

                                <!-- Shipping Fee Amount -->
                                <div class="col-md-4" id="wholesaleShippingFeeContainer" style="display: none;">
                                    <label class="form-label fw-bold">Shipping Fee Amount (₱)</label>
                                    <input type="number" step="0.01" id="wholesaleShippingFeeAmount" name="shipping_fee_amount" class="form-control calc-trigger" value="0.00" disabled>
                                </div>

                                <!-- Courier -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold">Courier / Delivered By</label>
                                    <input type="text" id="wholesaleCourier" name="courier" class="form-control uppercase-input" placeholder="e.g. Lalamove, AP Cargo">
                                </div>

                                <!-- Additional Discount (%) -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-danger">Additional Discount (%)</label>
                                    <input type="number" step="0.01" id="wholesaleAdditionalDiscount" name="additional_discount_percentage" class="form-control calc-trigger" value="0.00">
                                </div>

                                <!-- Withholding Tax (%) -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-warning-emphasis">Withholding Tax (%)</label>
                                    <input type="number" step="0.01" id="wholesaleWithholdingTaxPercent" name="withholding_tax" class="form-control calc-trigger" value="0.00" placeholder="e.g. 1% or 2%">
                                </div>

                                <!-- Withholding Tax Amount (Display only) -->
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-warning-emphasis">Withholding Tax Amt (₱)</label>
                                    <input type="text" id="wholesaleWithholdingTaxAmount" name="withholding_tax_amount" class="form-control bg-white fw-bold text-danger" readonly value="₱0.00">
                                </div>

                                <!-- Payment Proof Container -->
                                <div class="col-md-12 payment-proof-container" style="display: none;">
                                    <label class="form-label fw-bold">Payment Proof / Deposit Slip (Image/PDF)</label>
                                    <input type="file" name="proof_of_payment" class="form-control" accept="image/*,application/pdf" disabled>
                                </div>

                                <!-- Sub Total & Total Amount Summary Row -->
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Sub Total</label>
                                    <input type="text" id="wholesaleSubTotalDisplay" class="form-control bg-white fw-bold" readonly value="₱0.00">
                                    <input type="hidden" id="wholesaleSubTotal" name="sub_total" value="0.00">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-success">Total Amount</label>
                                    <input type="text" id="wholesaleGrandTotal" name="grand_total" class="form-control bg-white fw-bold text-success fs-5" readonly value="₱0.00">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- EXTRA FIELDS FOR ONLINE ORDERS -->
                    <div id="onlineExtraFields" class="mt-4 p-3 bg-light rounded border extra-fields-container" style="display: none;">
                        <h6 class="fw-bold text-info mb-3"><i class="fa-solid fa-globe me-1"></i> Online Order Tracking Details</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Contact #</label>
                                <input type="text" name="contact_number" class="form-control online-input" placeholder="Mobile / Phone number" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">MOP</label>
                                <select name="online_mop" class="form-select online-input mop-select" disabled>
                                    <option value="">-- Select MOP --</option>
                                    <option value="GCASH">GCASH</option>
                                    <option value="PAYMAYA">PAYMAYA</option>
                                    <option value="BDO">BDO</option>
                                    <option value="BPI">BPI</option>
                                    <option value="OTHERS">OTHERS</option>
                                </select>
                                <div class="custom-online-mop-container mt-2" style="display: none;">
                                    <label class="form-label fw-bold">Specify Other MOP</label>
                                    <input type="text" name="custom_mop" class="form-control online-input custom-online-mop-input uppercase-input" placeholder="Enter other payment method" disabled>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label fw-bold">Delivery Address</label>
                                <textarea name="address" class="form-control online-input" rows="2" placeholder="Complete delivery address" disabled></textarea>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Delivery Fee</label>
                                <input type="number" step="0.01" name="delivery_fee" id="onlineDeliveryFee" class="form-control calc-trigger" value="0" placeholder="0.00" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sub Total</label>
                                <input type="number" step="0.01" name="online_sub_total" id="onlineSubTotal" class="form-control bg-white" readonly placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold text-success">Total Amount</label>
                                <input type="number" step="0.01" name="grand_total" id="onlineGrandTotal" class="form-control bg-white fw-bold text-success" readonly placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Proof / Received Amount</label>
                                <input type="number" step="0.01" min="0" name="proof_amount" id="onlineProofAmount" class="form-control online-input calc-trigger" placeholder="0.00" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Difference</label>
                                <input type="number" step="0.01" id="onlineDifference" class="form-control bg-white fw-bold" value="0.00" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Remarks</label>
                                <input type="text" name="note" class="form-control online-input" placeholder="Optional reconciliation note" disabled>
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
                                    <option value="GCASH">GCASH</option>
                                    <option value="PAYMAYA">PAYMAYA</option>
                                    <option value="BDO">BDO</option>
                                    <option value="METROBANK">METROBANK</option>
                                    <option value="BPI">BPI</option>
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
        'label' => ($product->item_id ?: $product->id).' — '.($product->name ?: $product->description ?: 'Unnamed product'),
        'barcode' => $product->barcode,
        'description' => $product->description ?: $product->name,
        'sales' => $product->sales_price ?? 0,
        'wholesale' => $product->wholesale_price ?? 0,
        'shopee' => $product->shopee_price ?? 0,
        'lazada' => $product->lazada_price ?? 0,
        'tiktok' => $product->tiktok_price ?? 0,
    ])->values();
@endphp
<script>
document.addEventListener('DOMContentLoaded', function () {
    let rowIndex = 0;
    const channelSelect = document.getElementById('channelTypeSelect');
    const productOptions = @json($saleProductOptions);
    const productDataList = document.createElement('datalist');
    productDataList.id = 'saleProductOptions';
    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = `${product.label} | ${product.barcode || ''} | ${product.description || ''}`;
        productDataList.appendChild(option);
    });
    document.body.appendChild(productDataList);
    const bindProductSearch = row => {
        const input = row.querySelector('.product-search');
        const hidden = row.querySelector('.product-id');
        input.addEventListener('input', () => {
            const value = input.value.trim().toLowerCase();
            const product = productOptions.find(item =>
                `${item.label} | ${item.barcode || ''} | ${item.description || ''}`.toLowerCase() === value
            );
            hidden.value = product ? product.id : '';
            input.setCustomValidity(product ? '' : 'Select a product from the search suggestions.');
            if (product) {
                Object.entries(product).forEach(([key, item]) => row.dataset[key] = item ?? '');
            }
            updateRowDisplay(row);
            calculateTotals();
        });
    };
    bindProductSearch(document.querySelector('.product-row'));

    // Initialize Tom Select on the Channel Select dropdown if applicable, or bind events cleanly
    if (channelSelect) {
        channelSelect.addEventListener('change', handleChannelChange);
        if (channelSelect.value) {
            handleChannelChange();
        }
    }

    // Global Input Listener for Uppercase and Calculation Triggers
    document.addEventListener('input', function(e) {
        if (e.target && (e.target.classList.contains('custom-mop-input') || e.target.classList.contains('custom-bank-input') || e.target.classList.contains('custom-online-mop-input') || e.target.classList.contains('custom-walkin-mop-input') || e.target.classList.contains('mode-of-payment-others-input') || e.target.classList.contains('check-number-input') || e.target.id === 'wholesaleCourier')) {
            e.target.value = e.target.value.toUpperCase();
        }
        if (e.target.classList.contains('calc-trigger') || e.target.classList.contains('qty-input') || e.target.classList.contains('item-discount-input') || e.target.classList.contains('item-shipping-fee-input') || e.target.id === 'onlineDeliveryFee' || e.target.id === 'wholesaleShippingFeeAmount' || e.target.id === 'wholesaleAdditionalDiscount' || e.target.id === 'wholesaleWithholdingTaxPercent' || e.target.id === 'walkInAdditionalDiscount') {
            calculateTotals();
        }
    });

    // Main Channel Switch Handler
    function handleChannelChange() {
        const selectEl = document.getElementById('channelTypeSelect');
        const rawVal = selectEl ? (selectEl.value || '').trim() : '';

        // Hide and disable all extra field containers first
        const allContainerIds = [
            'marketplaceExtraFields', 
            'tiktokExtraFields', 
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

        // Toggle TikTok Shipping Column header rows
        const tiktokShippingCols = document.querySelectorAll('.tiktok-shipping-col');
        if (rawVal === 'tiktok') {
            tiktokShippingCols.forEach(col => col.style.display = '');
        } else {
            tiktokShippingCols.forEach(col => {
                col.style.display = 'none';
                const feeInput = col.querySelector('.item-shipping-fee-input');
                if (feeInput) feeInput.value = '0.00';
            });
        }

        // Map exact option values to their containers
        let activeContainerId = '';
        if (rawVal === 'shopee' || rawVal === 'lazada') {
            activeContainerId = 'marketplaceExtraFields';
        } else if (rawVal === 'tiktok') {
            activeContainerId = 'tiktokExtraFields';
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
                    if (el.id === 'onlineSubTotal' || el.id === 'onlineGrandTotal' || el.id === 'walkInSubTotal' || el.id === 'walkInGrandTotal' || el.id === 'shopeeSubTotal' || el.id === 'tiktokSubTotal' || el.id === 'wholesaleSubTotal' || el.id === 'wholesaleGrandTotal' || el.id === 'wholesaleWithholdingTaxAmount') {
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
                </div>
                <div class="col-md-1">
                    <label class="form-label fw-bold text-danger d-md-none">Discount %</label>
                    <input type="number" step="0.01" name="items[${rowIndex}][discount_percentage]" class="form-control item-discount-input calc-trigger" value="0.00" placeholder="0.00">
                </div>
                <div class="col-md-2 tiktok-shipping-col" style="${isTikTok ? '' : 'display: none;'}">
                    <label class="form-label fw-bold text-danger d-md-none" style="font-size: 11px;">Ship. Fee (5%)</label>
                    <input type="number" step="0.01" name="items[${rowIndex}][shipping_service_fee]" class="form-control item-shipping-fee-input bg-white text-danger fw-bold" value="0.00" readonly>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold text-success d-md-none">Total after Disc.</label>
                    <input type="text" class="form-control item-total-display bg-white fw-bold text-success" readonly value="₱0.00">
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
                alert('You must keep at least one product item.');
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
    }

    // Comprehensive Calculations for Subtotals and Total Amounts
    function calculateTotals() {
        let cumulativeSubTotal = 0;
        const channelVal = channelSelect ? (channelSelect.value || '').trim() : '';

        document.querySelectorAll('.product-row').forEach(row => {
            const unitPrice = parseFloat(row.getAttribute('data-unit-price')) || 0;
            const qtyInput = row.querySelector('.qty-input');
            const discountInput = row.querySelector('.item-discount-input');
            const shippingFeeInput = row.querySelector('.item-shipping-fee-input');
            const totalDisplay = row.querySelector('.item-total-display');

            const qty = qtyInput ? parseFloat(qtyInput.value) || 0 : 0;
            const discountPercent = discountInput ? parseFloat(discountInput.value) || 0 : 0;
            const baseAmount = unitPrice * qty;
            const discountAmount = baseAmount * (discountPercent / 100);
            const rowTotal = baseAmount - discountAmount;
            const shippingServiceFee = channelVal === 'tiktok' ? rowTotal * 0.05 : 0;

            cumulativeSubTotal += rowTotal;

            if (shippingFeeInput) {
                shippingFeeInput.value = shippingServiceFee.toFixed(2);
            }

            if (totalDisplay) {
                totalDisplay.value = '₱' + rowTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });

        // Assign to respective active channel total fields
        if (channelVal === 'shopee' || channelVal === 'lazada') {
            const subTotalInput = document.getElementById('shopeeSubTotal');
            const grandTotalInput = document.getElementById('shopeeGrandTotal');
            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = '₱' + cumulativeSubTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (channelVal === 'tiktok') {
            const subTotalInput = document.getElementById('tiktokSubTotal');
            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
        } else if (channelVal === 'wholesale') {
            const subTotalInput = document.getElementById('wholesaleSubTotal');
            const subTotalDisplay = document.getElementById('wholesaleSubTotalDisplay');
            const grandTotalInput = document.getElementById('wholesaleGrandTotal');
            const addDiscInput = document.getElementById('wholesaleAdditionalDiscount');
            const shipTypeSelect = document.getElementById('wholesaleShippingType');
            const shipFeeInput = document.getElementById('wholesaleShippingFeeAmount');
            
            // Withholding fields
            const wTaxPercentInput = document.getElementById('wholesaleWithholdingTaxPercent');
            const wTaxAmountDisplay = document.getElementById('wholesaleWithholdingTaxAmount');

            const addDiscPercent = addDiscInput ? parseFloat(addDiscInput.value) || 0 : 0;
            const wTaxPercent = wTaxPercentInput ? parseFloat(wTaxPercentInput.value) || 0 : 0;
            
            const shipType = shipTypeSelect ? shipTypeSelect.value : 'Free';
            const shipAmount = (shipType === 'Custom Amount' || shipType === 'COD') ? (shipFeeInput ? parseFloat(shipFeeInput.value) || 0 : 0) : 0;

            const discountedSubTotal = cumulativeSubTotal * (1 - (addDiscPercent / 100));
            const withholdingTaxAmount = discountedSubTotal * (wTaxPercent / 100);
            
            // Total Amount excludes withholding tax deduction per your preference
            const wholesaleGrandTotal = discountedSubTotal + shipAmount;

            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (subTotalDisplay) subTotalDisplay.value = '₱' + cumulativeSubTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            if (wTaxAmountDisplay) {
                wTaxAmountDisplay.value = '₱' + withholdingTaxAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            
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
            const proofAmount = proofAmountInput ? parseFloat(proofAmountInput.value) || 0 : 0;
            const onlineGrandTotal = cumulativeSubTotal + deliveryFee;
            const difference = proofAmount - cumulativeSubTotal - deliveryFee;

            if (subTotalInput) subTotalInput.value = cumulativeSubTotal.toFixed(2);
            if (grandTotalInput) grandTotalInput.value = onlineGrandTotal.toFixed(2);
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
            if (proofInput) proofInput.disabled = true;
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
            if (proofInput) proofInput.disabled = false;
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
            if (proofInput) proofInput.disabled = false;
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
            if (proofInput) proofInput.disabled = false;
        }
    }

    function evaluateOnlineMop(selectElement) {
        const mopVal = selectElement.value;
        const extraContainer = document.getElementById('onlineExtraFields');
        if (!extraContainer) return;

        const customMopContainer = extraContainer.querySelector('.custom-online-mop-container');
        const customMopInput = extraContainer.querySelector('.custom-online-mop-input');
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

        if (mopVal && mopVal !== '') {
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) proofInput.disabled = false;
        } else {
            if (proofContainer) proofContainer.style.display = 'none';
            if (proofInput) {
                proofInput.value = '';
                proofInput.disabled = true;
            }
        }
    }

    function evaluateWalkInMop(selectElement) {
        const mopVal = selectElement.value;
        const extraContainer = document.getElementById('walkInExtraFields');
        if (!extraContainer) return;

        const customMopContainer = extraContainer.querySelector('.custom-walkin-mop-container');
        const customMopInput = extraContainer.querySelector('.custom-walkin-mop-input');
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

        if (mopVal && mopVal !== '' && mopVal !== 'CASH') {
            if (proofContainer) proofContainer.style.display = 'block';
            if (proofInput) proofInput.disabled = false;
        } else {
            if (proofContainer) proofContainer.style.display = 'none';
            if (proofInput) {
                proofInput.value = '';
                proofInput.disabled = true;
            }
        }
    }

    // Event delegation for dynamic changes on sub-dropdowns
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('mop-select') && (e.target.closest('#marketplaceExtraFields') || e.target.classList.contains('marketplace-mop-container'))) {
            const mopVal = e.target.value;
            const parentCol = e.target.closest('.col-md-4');
            const othersInput = parentCol ? parentCol.querySelector('#mopOthersInput') : document.getElementById('mopOthersInput');
            const proofContainer = e.target.closest('.extra-fields-container') ? e.target.closest('.extra-fields-container').querySelector('.payment-proof-container') : null;
            const proofInput = proofContainer ? proofContainer.querySelector('input') : null;

            if (mopVal === 'OTHERS') {
                if (othersInput) {
                    othersInput.style.display = 'block';
                    othersInput.disabled = false;
                    othersInput.required = true;
                }
                if (proofContainer) proofContainer.style.display = 'block';
                if (proofInput) proofInput.disabled = false;
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

        if (e.target && e.target.classList.contains('bank-name-select')) {
            const extraContainer = e.target.closest('#wholesaleExtraFields');
            if (extraContainer) {
                const customBankContainer = extraContainer.querySelector('.custom-bank-container');
                const customBankInput = extraContainer.querySelector('.custom-bank-input');
                
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

    });

    const shippingTypeSelect = document.getElementById('wholesaleShippingType');
    const shippingFeeInput = document.getElementById('wholesaleShippingFeeAmount');
    const shippingContainer = document.getElementById('wholesaleShippingFeeContainer');
    
    function updateWholesaleShippingState(val) {
        if (val === 'Custom Amount' || val === 'COD') {
            if (shippingContainer) shippingContainer.style.display = 'block';
            if (shippingFeeInput) {
                shippingFeeInput.disabled = false;
                shippingFeeInput.type = 'number';
                shippingFeeInput.placeholder = '0.00';
                if (shippingFeeInput.value === '0.00' || !shippingFeeInput.value) shippingFeeInput.value = '0.00';
            }
        } else {
            if (shippingContainer) shippingContainer.style.display = 'none';
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
</script>
