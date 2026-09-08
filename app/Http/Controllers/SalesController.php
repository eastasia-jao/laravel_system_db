<?php

namespace App\Http\Controllers;

use App\Models\PendingSale;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StaffActivityLog;
use App\Models\StaffActivityLogItem;
use App\Models\StoreHub;
use App\Models\TransactionItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use App\Notifications\SalesWorkflowNotification;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    /**
     * Store a newly created sale in storage (Walk-In / Immediate stock reduction).
     */
    public function store(Request $request)
    {
        if ($request->has('product_id') && ! $request->has('items')) {
            $request->merge([
                'items' => [
                    [
                        'product_id' => $request->product_id,
                        'quantity' => $request->quantity ?? 1,
                        'unit_price' => $request->price ?? $request->unit_price ?? 0,
                    ],
                ],
            ]);
        }

        $request->validate([
            'store_hub_id' => 'required|exists:store_hubs,id',
            'sales_channel' => 'nullable|string',
            'channel_type' => 'nullable|string',
            'status' => 'nullable|string|in:completed,pending',
            'payment_status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'delivery_status' => ['nullable', Rule::in(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'])],
            'amount_paid' => 'nullable|numeric|min:0',
            'sales_after_transaction_fee' => 'nullable|numeric|min:0',
            'refund_shipping_fee' => 'nullable|numeric|min:0',
            'proof_amount' => 'nullable|numeric|min:0',
            'drop_off_date' => 'nullable|date',
            'note' => 'nullable|string|max:2000',
            'walkin_remarks' => 'nullable|string|max:2000',
            'order_date' => 'required|date',
            'order_number' => 'nullable|string|max:255',
            'customer_name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|between:0,100',
            'items.*.discount' => 'nullable|numeric|between:0,100',
        ]);

        $this->ensureHubAccess((int) $request->store_hub_id);

        try {
            DB::transaction(function () use ($request) {
                $channel = $request->sales_channel ?? $request->channel_type ?? 'Walk-In';
                $normalizedChannel = $this->normalizeSalesChannel($channel);
                if (auth()->user()?->role === 'sales_associate' && $normalizedChannel !== 'walk_in') {
                    abort(403, 'Sales Associates can only record Walk-In sales.');
                }
                if (auth()->user()?->role === 'sales_marketing_staff' && ! auth()->user()->hasSalesChannel($normalizedChannel)) {
                    abort(403, 'You are not assigned to record sales for this channel.');
                }
                $status = $request->input('status', 'completed');
                $paymentStatus = in_array($normalizedChannel, ['wholesale', 'walk_in'], true)
                    ? $request->input('payment_status', $normalizedChannel === 'walk_in' ? 'paid' : 'unpaid')
                    : 'not_applicable';
                $deliveryStatus = $normalizedChannel === 'wholesale'
                    ? $request->input('delivery_status', 'pending')
                    : 'not_applicable';

                $transaction = SalesTransaction::create([
                    'user_id' => auth()->id(),
                    'store_hub_id' => $request->store_hub_id,
                    'channel_type' => $channel,
                    'order_number' => $request->order_number ?? 'POS-'.time(),
                    'customer_name' => $request->customer_name ?? 'Walk-In Customer',
                    'order_date' => $request->order_date,
                    'date_of_arrangement' => $request->date_of_arrangement,
                    'location' => $request->location,
                    'mode_of_payment' => $request->mode_of_payment,
                    'check_date' => $request->check_date,
                    'check_number' => $request->check_number,
                    'delivery_date' => $request->delivery_date ?? $request->deliveryDate ?? $request->shipping_date,
                    'courier' => $request->courier ?? $request->courier_name ?? $request->shipping_courier,
                    'sub_total' => $request->sub_total ?? 0,
                    'total_amount' => $request->total_amount ?? 0,
                    'grand_total' => $request->grand_total ?? 0,
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'amount_paid' => $request->input('amount_paid', $paymentStatus === 'paid' ? $request->input('grand_total', 0) : 0),
                    'delivery_status' => $deliveryStatus,
                    'sales_after_transaction_fee' => $request->input('sales_after_transaction_fee'),
                    'refund_shipping_fee' => $request->input('refund_shipping_fee', 0),
                    'proof_amount' => $request->input('proof_amount'),
                    'drop_off_date' => $request->input('drop_off_date'),
                    'note' => $request->input('note') ?? $request->input('walkin_remarks'),
                ]);

                foreach ($request->items as $itemData) {
                    $product = Product::where('id', $itemData['product_id'])
                        ->where('store_hub_id', $request->store_hub_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $quantity = $itemData['quantity'];

                    if ($status === 'completed' && $product->stock < $quantity) {
                        throw new \RuntimeException("Not enough stock for \"{$product->name}\" (have {$product->stock}, need {$quantity}).");
                    }

                    $unitPrice = $itemData['unit_price'] ?? $itemData['price'] ?? $product->sales_price ?? 0;
                    $discountPct = $itemData['discount_percentage'] ?? $itemData['discount'] ?? 0;
                    $lineTotal = ($unitPrice * $quantity) * (1 - ($discountPct / 100));

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_percentage' => $discountPct,
                        'line_total' => $lineTotal,
                    ]);

                    if ($status === 'completed') {
                        $product->decrement('stock', $quantity);
                        InventoryTransaction::create([
                            'type' => 'sold',
                            'reference' => $transaction->order_number,
                            'store_hub_id' => $product->store_hub_id,
                            'product_id' => $product->id,
                            'channel' => $normalizedChannel,
                            'quantity' => $quantity,
                            'occurred_on' => $transaction->order_date,
                            'created_by' => auth()->id(),
                        ]);
                    }
                }
            });

            $successMessage = $request->input('status') === 'pending'
                ? 'Sale successfully saved as pending!'
                : 'Sale recorded successfully and inventory updated!';

            return back()->with('success', $successMessage);

        } catch (\Exception $e) {
            return back()->with('error', 'Failed to record sale: '.$e->getMessage());
        }
    }

    /**
     * Store Multi-Channel Sale submissions into the pending_sales queue.
     */
    public function storeMultiChannelSale(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_hub_id' => 'required|exists:store_hubs,id',
            'sales_channel' => 'nullable|string',
            'channel_type' => 'nullable|string',
            'placed_order_date' => 'nullable|date',
            'order_date' => 'nullable|date',
            'date_of_arrangement' => 'nullable|date',
            'delivery_date' => 'nullable|date',
            'deliveryDate' => 'nullable|date',
            'shipping_date' => 'nullable|date',
            'courier' => 'nullable|string',
            'courier_name' => 'nullable|string',
            'shipping_courier' => 'nullable|string',
            'check_date' => 'nullable|date',
            'check_number' => 'nullable|string',
            'address' => 'nullable|string',
            'delivery_address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.price' => 'nullable|numeric|min:0',
            'items.*.discount_percentage' => 'nullable|numeric|between:0,100',
            'items.*.discount' => 'nullable|numeric|between:0,100',
            'shipping_fee_amount' => 'nullable|numeric|min:0',
            'shipping_fee' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'additional_discount_percentage' => 'nullable|numeric|between:0,100',
            'withholding_tax' => 'nullable|numeric|between:0,100',
            'withholding_tax_amount' => 'nullable|numeric|min:0',
            'payment_status' => ['nullable', Rule::in(['unpaid', 'partial', 'paid'])],
            'delivery_status' => ['nullable', Rule::in(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'])],
            'amount_paid' => 'nullable|numeric|min:0',
            'sales_after_transaction_fee' => 'nullable|numeric|min:0',
            'refund_shipping_fee' => 'nullable|numeric|min:0',
            'proof_amount' => 'nullable|numeric|min:0',
            'drop_off_date' => 'nullable|date',
            'note' => 'nullable|string|max:2000',
            'walkin_remarks' => 'nullable|string|max:2000',
            'custom_mop' => 'nullable|string|max:255',
            'proof_of_payment' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'order_slip' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $this->ensureHubAccess((int) $request->store_hub_id);
        $channel = $this->normalizeSalesChannel($request->sales_channel ?? $request->channel_type ?? 'online');
        $isTiktok = $channel === 'tiktok';
        if (auth()->user()?->role === 'sales_associate' && $channel !== 'walk_in') {
            abort(403, 'Sales Associates can only record Walk-In sales.');
        }
        if (auth()->user()?->role === 'sales_marketing_staff' && ! auth()->user()->hasSalesChannel($channel)) {
            abort(403, 'You are not assigned to record sales for this channel.');
        }

        $orderSlipPath = null;
        try {
            $proofPath = null;
            if ($request->hasFile('proof_of_payment')) {
                $proofPath = $request->file('proof_of_payment')->store('proofs_of_payment', 'public');
            }

            $mop = $request->input('mode_of_payment') ?? $request->input('online_mop') ?? $request->input('walkin_mop');
            if ($mop === 'OTHERS') {
                $mop = $request->input('custom_mop') ?? $request->input('mode_of_payment_others');
            }

            $formattedItems = [];
            $subTotal = 0;
            $requestedQuantities = [];
            $availableStocks = [];

            foreach ($request->input('items', []) as $item) {
                $product = Product::where('id', $item['product_id'])
                    ->where('store_hub_id', $request->store_hub_id)
                    ->firstOrFail();

                $quantity = $item['quantity'];
                $unitPrice = $item['unit_price'] ?? $item['price'] ?? $product->sales_price ?? 0;
                $discountPct = $item['discount_percentage'] ?? $item['discount'] ?? 0;

                $lineTotal = ($unitPrice * $quantity) * (1 - ($discountPct / 100));
                $subTotal += $lineTotal;

                $formattedItems[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percentage' => $discountPct,
                    'line_total' => $lineTotal,
                ];

                $requestedQuantities[$product->id] = ($requestedQuantities[$product->id] ?? 0) + $quantity;
                $availableStocks[$product->id] = (int) $product->stock;
            }

            $inventoryReady = collect($requestedQuantities)->every(
                fn ($quantity, $productId) => ($availableStocks[$productId] ?? 0) >= $quantity
            );

            $shippingFee = $request->input('shipping_fee_amount') ?? $request->input('shipping_fee') ?? $request->input('delivery_fee') ?? 0;
            $additionalDiscountPct = $request->input('additional_discount_percentage') ?? 0;
            $channel = strtolower((string) ($request->sales_channel ?? $request->channel_type ?? 'online'));
            $shippingServiceFee = $channel === 'tiktok' ? round($subTotal * 0.05, 2) : 0;

            $withholdingTaxPct = $request->input('withholding_tax') ?? 0;
            $withholdingTaxAmount = $request->input('withholding_tax_amount');

            if (! $withholdingTaxAmount && $withholdingTaxPct > 0) {
                $withholdingTaxAmount = $subTotal * ($withholdingTaxPct / 100);
            }
            $withholdingTaxAmount = $withholdingTaxAmount ?? 0;

            $discountAmount = $subTotal * ($additionalDiscountPct / 100);
            $grandTotal = max(0, ($subTotal - $discountAmount) + $shippingFee - $withholdingTaxAmount);
            $paymentStatus = in_array($channel, ['wholesale', 'walk_in'], true)
                ? $request->input('payment_status', $channel === 'walk_in' ? 'paid' : 'unpaid')
                : 'not_applicable';
            $deliveryStatus = $channel === 'wholesale'
                ? $request->input('delivery_status', 'pending')
                : 'not_applicable';
            $amountPaid = (float) $request->input('amount_paid', 0);
            if ($paymentStatus === 'paid') {
                $amountPaid = $grandTotal;
            } elseif ($paymentStatus === 'unpaid' || $paymentStatus === 'not_applicable') {
                $amountPaid = 0;
            }

            // Flexible mapping for delivery date and courier capturing all possible request keys
            $deliveryDate = $request->input('delivery_date') ?? $request->input('deliveryDate') ?? $request->input('shipping_date');
            $courier = $request->input('courier') ?? $request->input('courier_name') ?? $request->input('shipping_courier');

            if ($request->hasFile('order_slip')) {
                $orderSlipPath = $request->file('order_slip')->store('order_slips', 'local');
                if (! $orderSlipPath) {
                    throw new \RuntimeException('Could not save the order slip. Please try again.');
                }
            }

            $pendingSale = PendingSale::create([
                'order_slip' => $orderSlipPath,
                'store_hub_id' => $request->store_hub_id,
                'sales_channel' => $request->sales_channel ?? $request->channel_type ?? 'Online',
                'placed_order_date' => $request->placed_order_date ?? $request->order_date ?? now(),
                'date_of_arrangement' => $request->input('date_of_arrangement'),
                'delivery_date' => $deliveryDate,
                'courier' => $isTiktok ? null : $courier,
                'customer_name' => $request->customer_name,
                'invoice_number' => $request->invoice_number ?? $request->order_number,
                'contact_number' => $request->contact_number,
                'delivery_address' => $request->address ?? $request->delivery_address,
                'location' => $request->input('location'),
                'mode_of_payment' => $mop,
                'custom_mop' => $request->custom_mop,
                'bank_name' => $request->bank_name,
                'custom_bank_name' => $request->custom_bank_name,
                'check_number' => $request->input('check_number'),
                'check_date' => $request->input('check_date'),
                'shipping_fee_amount' => $shippingFee,
                'delivery_fee' => $shippingFee,
                'shipping_service_fee' => $shippingServiceFee,
                'sales_after_transaction_fee' => $request->input('sales_after_transaction_fee'),
                'refund_shipping_fee' => $isTiktok ? 0 : $request->input('refund_shipping_fee', 0),
                'proof_amount' => $request->input('proof_amount'),
                'shipping_fee_type' => $request->input('shipping_fee_type'),
                'additional_discount_percentage' => $additionalDiscountPct,
                'withholding_tax' => $withholdingTaxPct,
                'withholding_tax_amount' => $withholdingTaxAmount,
                'sub_total' => $subTotal,
                'total' => $grandTotal,
                'grand_total' => $grandTotal,
                'payment_proof' => $proofPath,
                'items' => $formattedItems,
                'status' => 'pending',
                'inventory_status' => $inventoryReady ? 'ready' : 'awaiting_stock',
                'payment_status' => $paymentStatus,
                'amount_paid' => $amountPaid,
                'delivery_status' => $deliveryStatus,
                'drop_off_date' => $isTiktok ? null : $request->input('drop_off_date'),
                'note' => $isTiktok ? null : ($request->input('note') ?? $request->input('walkin_remarks')),
                'submitted_by' => auth()->id(),
            ]);
            $recipients = User::where(function ($query) use ($request) {
                $query->where('role', 'admin')
                    ->orWhere('role', 'inventory_staff');
            })->where('id', '<>', auth()->id())->get();
            foreach ($recipients as $recipient) {
                $recipient->notify(new SalesWorkflowNotification('submitted', $pendingSale));
            }

            return back()->with('success', 'Order submitted for inventory verification. Stock has not been deducted yet.');
        } catch (\Exception $e) {
            if ($orderSlipPath) {
                Storage::disk('local')->delete($orderSlipPath);
            }
            return back()->with('error', 'Failed to submit multi-channel sale: '.$e->getMessage());
        }
    }

    public function orderSlip($id)
    {
        $sale = PendingSale::findOrFail($id);
        $this->ensureHubAccess((int) $sale->store_hub_id);
        abort_unless($sale->order_slip && Storage::disk('local')->exists($sale->order_slip), 404);

        return Storage::disk('local')->response($sale->order_slip, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function rejectedOrderSlip($id)
    {
        $query = PendingSale::whereKey($id)->where('status', 'rejected');
        if (! in_array(auth()->user()?->role, ['admin', 'inventory_staff'], true)) {
            $query->where('submitted_by', auth()->id());
        }
        $sale = $query->firstOrFail();

        abort_unless($sale->order_slip && Storage::disk('local')->exists($sale->order_slip), 404);

        return Storage::disk('local')->response($sale->order_slip, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function confirmPendingSale($id)
    {
        $pendingSale = PendingSale::findOrFail($id);

        $user = auth()->user();
        if ($user && $user->store_hub_id && $pendingSale->store_hub_id != $user->store_hub_id) {
            abort(403, 'Unauthorized action. You cannot confirm pending sales for another store hub.');
        }

        if ($pendingSale->status !== 'pending') {
            return back()->with('error', 'This order has already been processed.');
        }

        try {
            DB::transaction(function () use ($id) {
                $pendingSale = PendingSale::whereKey($id)->lockForUpdate()->firstOrFail();

                if ($pendingSale->status !== 'pending') {
                    throw new \RuntimeException('This order has already been processed.');
                }

                // Lock and validate every requested product before creating a
                // transaction or deducting any stock. This keeps the order atomic.
                $availability = $this->inventoryAvailability($pendingSale, true);

                if (! $availability['ready']) {
                    $shortages = collect($availability['products'])
                        ->filter(fn ($item) => $item['shortage'] > 0)
                        ->map(fn ($item) => sprintf(
                            '%s (requested %d, available %d, short %d)',
                            $item['name'],
                            $item['requested'],
                            $item['available'],
                            $item['shortage']
                        ))
                        ->implode('; ');

                    throw new \DomainException('Awaiting stock: '.$shortages.'. No stock was deducted.');
                }

                // Fallbacks to grab delivery date or courier from alternative columns if main ones are null
                $resolvedDeliveryDate = $pendingSale->delivery_date
                    ?? $pendingSale->date_of_arrangement
                    ?? $pendingSale->shipping_date
                    ?? null;

                $resolvedCourier = $pendingSale->courier
                    ?? $pendingSale->courier_name
                    ?? $pendingSale->shipping_courier
                    ?? null;

                $channel = $this->normalizeSalesChannel($pendingSale->sales_channel);
                $paymentStatus = in_array($channel, ['wholesale', 'walk_in'], true)
                    ? ($pendingSale->payment_status ?? ($channel === 'walk_in' ? 'paid' : 'unpaid'))
                    : 'not_applicable';
                $deliveryStatus = $channel === 'wholesale'
                    ? ($pendingSale->delivery_status ?? 'pending')
                    : 'not_applicable';

                $transaction = SalesTransaction::create([
                    'user_id' => $pendingSale->submitted_by,
                    'store_hub_id' => $pendingSale->store_hub_id,
                    'channel_type' => $pendingSale->sales_channel,
                    'order_date' => $pendingSale->placed_order_date ?? now(),
                    'date_of_arrangement' => $pendingSale->date_of_arrangement ?? null,
                    'location' => $pendingSale->location ?? null,
                    'order_number' => $pendingSale->invoice_number ?? 'PENDING-'.$pendingSale->id,
                    'customer_name' => $pendingSale->customer_name ?? 'Online Customer',
                    'contact_number' => $pendingSale->contact_number,
                    'address' => $pendingSale->delivery_address ?? $pendingSale->address ?? null,
                    'mode_of_payment' => $pendingSale->mode_of_payment,
                    'custom_mop' => $pendingSale->custom_mop,
                    'bank_name' => $pendingSale->bank_name,
                    'custom_bank_name' => $pendingSale->custom_bank_name,

                    'check_number' => $pendingSale->check_number,
                    'check_date' => $pendingSale->check_date ?? null,

                    // Fixed mappings with fallbacks
                    'delivery_date' => $resolvedDeliveryDate,
                    'courier' => $resolvedCourier,

                    'proof_of_payment' => $pendingSale->payment_proof ?? $pendingSale->proof_of_payment,
                    'order_slip' => $pendingSale->order_slip,
                    'shipping_fee_amount' => $pendingSale->shipping_fee_amount ?? 0,
                    'shipping_service_fee' => $pendingSale->shipping_service_fee ?? 0,
                    'sales_after_transaction_fee' => $pendingSale->sales_after_transaction_fee,
                    'refund_shipping_fee' => $pendingSale->refund_shipping_fee ?? 0,
                    'proof_amount' => $pendingSale->proof_amount,
                    'shipping_fee_type' => $pendingSale->shipping_fee_type ?? null,
                    'additional_discount_percentage' => $pendingSale->additional_discount_percentage ?? 0,
                    'withholding_tax' => $pendingSale->withholding_tax ?? 0,
                    'withholding_tax_amount' => $pendingSale->withholding_tax_amount ?? 0,
                    'sub_total' => $pendingSale->sub_total ?? 0,
                    'total_amount' => $pendingSale->total ?? $pendingSale->sub_total ?? 0,
                    'grand_total' => $pendingSale->grand_total ?? 0,
                    'status' => 'confirmed',
                    'payment_status' => $paymentStatus,
                    'amount_paid' => $pendingSale->amount_paid ?? 0,
                    'delivery_status' => $deliveryStatus,
                    'drop_off_date' => $pendingSale->drop_off_date,
                    'note' => $pendingSale->note,
                ]);

                $verificationItems = [];

                foreach ($pendingSale->items as $itemData) {
                    $product = $availability['models']->get((int) $itemData['product_id']);

                    $quantity = $itemData['quantity'];

                    $unitPrice = $itemData['unit_price'] ?? $itemData['price'] ?? $product->sales_price ?? 0;
                    $discountPct = $itemData['discount_percentage'] ?? 0;
                    $lineTotal = ($unitPrice * $quantity) * (1 - ($discountPct / 100));

                    TransactionItem::create([
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_percentage' => $discountPct,
                        'line_total' => $lineTotal,
                    ]);

                    $stockBefore = (int) $product->stock;
                    $product->decrement('stock', $quantity);
                    InventoryTransaction::create([
                        'type' => 'sold',
                        'reference' => $transaction->order_number,
                        'store_hub_id' => $product->store_hub_id,
                        'product_id' => $product->id,
                        'channel' => $channel,
                        'quantity' => $quantity,
                        'occurred_on' => $transaction->order_date,
                        'created_by' => auth()->id(),
                    ]);
                    $verificationItems[] = [
                        'product_id' => $product->id,
                        'item_id' => $product->item_id,
                        'product_name' => $product->name ?: ($itemData['product_name'] ?? 'Unnamed product'),
                        'operation' => 'deducted',
                        'quantity' => (int) $quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockBefore - (int) $quantity,
                        'details' => null,
                    ];
                }

                $pendingSale->update([
                    'status' => 'confirmed',
                    'inventory_status' => 'verified',
                    'confirmed_by' => auth()->id(),
                    'confirmed_at' => now(),
                ]);
                if ($pendingSale->submitted_by && $pendingSale->submitted_by !== auth()->id()) {
                    User::find($pendingSale->submitted_by)?->notify(
                        new SalesWorkflowNotification('confirmed', $pendingSale->fresh())
                    );
                }

                $activityLog = StaffActivityLog::create([
                    'user_id' => auth()->id(),
                    'store_hub_id' => $pendingSale->store_hub_id,
                    'action_type' => 'inventory_verification',
                    'description' => sprintf(
                        'Verified order %s and deducted stock for %d product item(s).',
                        $pendingSale->invoice_number ?: '#'.$pendingSale->id,
                        count($verificationItems)
                    ),
                    'details' => [
                        'pending_sale_id' => $pendingSale->id,
                        'transaction_id' => $transaction->id,
                        'invoice_number' => $pendingSale->invoice_number,
                        'sales_channel' => $pendingSale->sales_channel,
                        'item_count' => count($verificationItems),
                    ],
                    'ip_address' => request()->ip(),
                ]);
                User::whereIn('role', ['admin', 'inventory_staff'])
                    ->where('id', '<>', auth()->id())
                    ->get()
                    ->each(fn (User $recipient) => $recipient->notify(
                        new InventoryWorkflowNotification(
                            'inventory_verification',
                            sprintf(
                                '%s verified order %s for %s.',
                                auth()->user()->name,
                                $pendingSale->invoice_number ?: '#'.$pendingSale->id,
                                $pendingSale->storeHub?->name ?? 'the store hub'
                            ),
                            (int) $pendingSale->store_hub_id,
                            route('hub.sales.pending', $pendingSale->store_hub_id)
                        )
                    ));

                if ($verificationItems !== []) {
                    StaffActivityLogItem::insert(array_map(fn ($item) => [
                        ...$item,
                        'staff_activity_log_id' => $activityLog->id,
                    ], $verificationItems));
                }
            });

            return back()->with('success', 'Inventory verified and stock deducted successfully.');
        } catch (\DomainException $e) {
            PendingSale::whereKey($id)
                ->where('status', 'pending')
                ->update(['inventory_status' => 'awaiting_stock']);

            return back()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to confirm sale: '.$e->getMessage());
        }
    }

    public function rejectPendingSale(Request $request, $id)
    {
        $pendingSale = PendingSale::findOrFail($id);
        $this->ensureHubAccess((int) $pendingSale->store_hub_id);

        if ($pendingSale->status !== 'pending') {
            return back()->with('error', 'This order has already been processed.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:2000'],
        ]);

        $pendingSale->update([
            'status' => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        if ($pendingSale->submitted_by && (int) $pendingSale->submitted_by !== (int) auth()->id()) {
            User::find($pendingSale->submitted_by)?->notify(
                new SalesWorkflowNotification('rejected', $pendingSale->fresh())
            );
        }

        return back()->with('success', 'The sale was rejected and the submitting user was notified.');
    }

    public function recordSale(Request $request)
    {
        return $this->store($request);
    }

    public function updateStatus(Request $request, int $id)
    {
        $sale = SalesTransaction::findOrFail($id);

        $this->ensureHubAccess((int) $sale->store_hub_id);

        $channel = $this->normalizeSalesChannel($sale->channel_type);
        if (! in_array($channel, ['wholesale', 'walk_in'], true)) {
            abort(403, 'Statuses are only managed for Wholesale and Walk-In sales.');
        }

        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid'])],
            'delivery_status' => [Rule::requiredIf($channel === 'wholesale'), 'nullable', Rule::in(['pending', 'preparing', 'shipped', 'delivered', 'cancelled'])],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($channel === 'wholesale' && $sale->payment_status === 'paid' && $validated['payment_status'] !== 'paid') {
            return back()->withErrors([
                'payment_status' => 'A fully paid wholesale order cannot be changed back to unpaid or partial.',
            ]);
        }

        $grandTotal = (float) ($sale->grand_total ?? $sale->total_amount ?? $sale->total ?? 0);
        $amountPaid = (float) ($validated['amount_paid'] ?? 0);

        if ($validated['payment_status'] === 'paid') {
            $amountPaid = $grandTotal;
        } elseif ($validated['payment_status'] === 'unpaid') {
            $amountPaid = 0;
        } elseif ($amountPaid <= 0 || $amountPaid >= $grandTotal) {
            return back()->withErrors([
                'amount_paid' => 'For a partially paid order, the amount paid must be greater than zero and less than the grand total.',
            ]);
        }

        $sale->update([
            'payment_status' => $validated['payment_status'],
            'amount_paid' => $amountPaid,
            'delivery_status' => $channel === 'wholesale'
                ? $validated['delivery_status']
                : 'not_applicable',
        ]);

        return back()->with('success', $channel === 'wholesale'
            ? 'Wholesale payment and delivery statuses updated.'
            : 'Walk-In payment status updated.');
    }

    public function pendingSalesIndex(Request $request, $hubId = null)
    {
        $user = auth()->user();

        if (! in_array($user?->role, ['admin', 'inventory_staff'], true)) {
            return redirect()->route('dashboard')->with('notification_error',
                'The Inventory Verification Queue is only available to inventory staff and admins. You can read the verification update in your notifications.');
        }

        if (! $hubId && $user && $user->store_hub_id && $user->role !== 'inventory_staff' && $user->role !== 'admin') {
            $hubId = $user->store_hub_id;
        }

        if ($hubId && $user && ! $user->canAccessHub((int) $hubId)) {
            abort(403, 'Unauthorized access to this store hub pending sales.');
        }

        $hub = $hubId ? StoreHub::find($hubId) : null;
        $query = PendingSale::with(['storeHub', 'submittedBy'])->where('status', 'pending');

        if ($hubId) {
            $query->where('store_hub_id', $hubId);
        }

        $pendingSales = $query->orderBy('created_at', 'desc')->paginate(10);

        $pendingSales->getCollection()->each(function (PendingSale $sale) {
            $sale->setAttribute('inventory_availability', $this->inventoryAvailability($sale));
        });

        return view('hubs.pending-sales', compact('hub', 'pendingSales'));
    }

    public function rejectedSalesIndex()
    {
        $user = auth()->user();
        $query = PendingSale::with(['storeHub', 'rejectedBy', 'submittedBy'])
            ->where('status', 'rejected');

        if (! in_array($user?->role, ['admin', 'inventory_staff'], true)) {
            $query->where('submitted_by', $user->id);
        }

        $rejectedSales = $query
            ->latest('rejected_at')
            ->paginate(10);

        return view('hubs.rejected-sales', compact('rejectedSales'));
    }

    /**
     * Return the live stock position for all products in a pending order.
     * Quantities are grouped by product so duplicate lines cannot bypass the check.
     */
    private function inventoryAvailability(PendingSale $pendingSale, bool $lockForUpdate = false): array
    {
        $items = collect($pendingSale->items ?? []);
        $requested = $items
            ->filter(fn ($item) => isset($item['product_id']))
            ->groupBy(fn ($item) => (int) $item['product_id'])
            ->map(fn ($productItems) => $productItems->sum(fn ($item) => (int) ($item['quantity'] ?? 0)));

        $query = Product::where('store_hub_id', $pendingSale->store_hub_id)
            ->whereIn('id', $requested->keys());

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $models = $query->get()->keyBy('id');
        $products = $requested->mapWithKeys(function ($quantity, $productId) use ($models) {
            $product = $models->get((int) $productId);
            $available = max(0, (int) ($product?->stock ?? 0));

            return [(int) $productId => [
                'name' => $product?->name ?? 'Unavailable product #'.$productId,
                'requested' => (int) $quantity,
                'available' => $available,
                'shortage' => max(0, (int) $quantity - $available),
            ]];
        });

        $hasUnresolvedItems = $items->contains(fn ($item) => empty($item['product_id']));
        $ready = ! $hasUnresolvedItems
            && $requested->isNotEmpty()
            && $products->every(fn ($item) => $item['shortage'] === 0);

        return [
            'ready' => $ready,
            'products' => $products->all(),
            'models' => $models,
        ];
    }

    private function ensureHubAccess(int $hubId): void
    {
        $user = auth()->user();

        if ($user && ! $user->canAccessHub($hubId)) {
            abort(403, 'Unauthorized action for this store hub.');
        }
    }

    private function normalizeSalesChannel(?string $channel): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim((string) $channel)));
    }
}
