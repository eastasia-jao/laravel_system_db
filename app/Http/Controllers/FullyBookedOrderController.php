<?php

namespace App\Http\Controllers;

use App\Models\FullyBookedOrder;
use App\Models\FullyBookedOrderItem;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\SalesTransaction;
use App\Models\StoreHub;
use App\Models\User;
use App\Notifications\InventoryWorkflowNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FullyBookedOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($this->canViewOrders($user), 403);

        return redirect()->route('inventory-transactions.index', array_merge(
            $request->only(['hub_id', 'month']),
            ['type' => 'fully_booked', 'status' => $request->input('status')]
        ));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $user->role === 'sales_marketing_staff' && $user->hasSalesChannel('fully_booked')
                && $user->status === 'active',
            403,
            'Fully Booked order submission is only available to active Sales/Marketing staff designated for this channel.'
        );
        $activeStoreIds = StoreHub::where('status', 'active')->pluck('id')->map(fn ($id) => (string) $id);
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'store_selection' => ['required', Rule::in($activeStoreIds->push('other')->all())],
            'other_store_name' => ['nullable', 'required_if:store_selection,other', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $storeName = $validated['store_selection'] === 'other'
            ? trim($validated['other_store_name'])
            : StoreHub::whereKey($validated['store_selection'])->where('status', 'active')->value('name');
        $salesStaff = $user;

        $file = $validated['attachment'];
        $path = $file->store('fully-booked-orders', 'local');
        if (! $path) {
            throw new \RuntimeException('The Fully Booked attachment could not be stored.');
        }

        try {
            $order = DB::transaction(function () use ($salesStaff, $user, $path, $file, $validated, $storeName) {
                $order = FullyBookedOrder::create([
                    'order_number' => 'PENDING-'.Str::uuid(),
                    'store_hub_id' => $salesStaff->hub_id,
                    'store_name' => $storeName,
                    'sales_staff_id' => $salesStaff->id,
                    'submitted_by' => $user->id,
                    'attachment_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'remarks' => $validated['remarks'] ?? null,
                    'status' => 'pending',
                ]);
                $order->update(['order_number' => 'FB-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)]);

                return $order;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        $reviewers = User::whereIn('role', ['admin', 'inventory_staff'])
            ->where('status', 'active')
            ->where('id', '<>', $user->id)
            ->get();
        foreach ($reviewers as $reviewer) {
            $reviewer->notify(new InventoryWorkflowNotification(
                'fully_booked_order',
                sprintf(
                    '%s submitted Fully Booked order %s for attachment review and item pull-out.',
                    $salesStaff->name,
                    $order->order_number
                ),
                $order->store_hub_id,
                route('inventory-transactions.sponsor.create', [
                    'hub_id' => $order->store_hub_id,
                    'activity_type' => 'fully_booked',
                ]),
                reference: $order->order_number
            ));
        }

        if ($user->role === 'sales_marketing_staff') {
            return redirect()->route('inventory-transactions.sponsor.create', [
                'hub_id' => $salesStaff->hub_id,
                'activity_type' => 'fully_booked',
            ])->with('success', 'Fully Booked order '.$order->order_number.' submitted for review.');
        }

        return redirect()->route('inventory-transactions.fully-booked.index', [
            'search' => $order->order_number,
            'fully_booked_submitted' => 1,
        ]);
    }

    public function attachment(Request $request, FullyBookedOrder $fullyBookedOrder)
    {
        abort_unless($this->canViewOrder($request->user(), $fullyBookedOrder), 403);

        $path = Storage::disk('local')->path($fullyBookedOrder->attachment_path);
        abort_unless(is_file($path), 404, 'The Fully Booked attachment is no longer available.');

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $fullyBookedOrder->original_filename) ?: 'fully-booked-attachment';

        return response()->file($path, [
            'Content-Type' => $fullyBookedOrder->mime_type,
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pullOut(Request $request, FullyBookedOrder $fullyBookedOrder)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'inventory_staff'], true), 403);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($request, $fullyBookedOrder, $validated) {
            $order = FullyBookedOrder::whereKey($fullyBookedOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->store_hub_id, 422, 'This Fully Booked order has no assigned store hub.');
            abort_unless(is_null($order->pulled_out_at), 409, 'The items for this Fully Booked order have already been pulled out.');

            $sale = SalesTransaction::firstOrCreate([
                'store_hub_id' => $order->store_hub_id,
                'channel_type' => 'fully_booked',
                'order_number' => $order->order_number,
            ], [
                'order_date' => now()->toDateString(),
                'customer_name' => 'Fully Booked',
                'status' => 'confirmed',
                'user_id' => $order->submitted_by,
            ]);

            foreach ($validated['items'] as $entry) {
                $product = Product::whereKey($entry['product_id'])->lockForUpdate()->firstOrFail();
                if ((int) $product->store_hub_id !== (int) $order->store_hub_id || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'items' => 'Choose active products from '.$order->storeHub?->name.'.',
                    ]);
                }
                $quantity = (int) $entry['quantity'];
                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => "Not enough stock for {$product->name}. Available: {$product->stock}.",
                    ]);
                }

                $product->decrement('stock', $quantity);
                $transaction = InventoryTransaction::create([
                    'reference' => $order->order_number,
                    'type' => 'sponsor_workshop',
                    'store_hub_id' => $order->store_hub_id,
                    'product_id' => $product->id,
                    'channel' => 'fully_booked',
                    'source' => 'fully_booked',
                    'quantity' => $quantity,
                    'occurred_on' => now()->toDateString(),
                    'notes' => 'Fully Booked attachment pull-out',
                    'created_by' => $request->user()->id,
                ]);
                FullyBookedOrderItem::create([
                    'fully_booked_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name ?: $product->description ?: 'Unknown product',
                    'item_id' => $product->item_id,
                    'quantity' => $quantity,
                    'inventory_transaction_id' => $transaction->id,
                ]);
                $sale->items()->updateOrCreate([
                    'product_id' => $product->id,
                ], [
                    'quantity' => $quantity,
                    'unit_price' => 0,
                    'discount_percentage' => 0,
                    'line_total' => 0,
                ]);
            }

            $order->update([
                'pulled_out_by' => $request->user()->id,
                'pulled_out_at' => now(),
            ]);
        });

        $submitter = $fullyBookedOrder->submitter;
        if ($submitter) {
            $submitter->notify(new InventoryWorkflowNotification(
                'fully_booked_completed',
                sprintf(
                    'Inventory staff completed the stock update for your Fully Booked order %s.',
                    $fullyBookedOrder->order_number
                ),
                $fullyBookedOrder->store_hub_id,
                route('inventory-transactions.sponsor.create', [
                    'hub_id' => $fullyBookedOrder->store_hub_id,
                    'activity_type' => 'fully_booked',
                ]),
                reference: $fullyBookedOrder->order_number
            ));
        }

        return redirect()->route('inventory-transactions.sponsor.create', [
            'hub_id' => $fullyBookedOrder->store_hub_id,
            'activity_type' => 'fully_booked',
        ])->with('success', 'Fully Booked order completed and stock updated.');
    }

    public function review(Request $request, FullyBookedOrder $fullyBookedOrder)
    {
        abort_unless(in_array($request->user()->role, ['admin', 'inventory_staff'], true), 403);

        DB::transaction(function () use ($request, $fullyBookedOrder) {
            $order = FullyBookedOrder::whereKey($fullyBookedOrder->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->status === 'pending', 409, 'This attachment has already been reviewed.');
            $order->update([
                'status' => 'reviewed',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        });

        return back()->with('success', 'Fully Booked attachment marked as reviewed.');
    }

    private function canViewOrders(User $user): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true);
    }

    private function canViewOrder(User $user, FullyBookedOrder $order): bool
    {
        return in_array($user->role, ['admin', 'inventory_staff'], true)
            || ($user->role === 'sales_marketing_staff'
                && $user->hasSalesChannel('fully_booked')
                && ((int) $order->submitted_by === (int) $user->id || (int) $order->sales_staff_id === (int) $user->id));
    }
}
