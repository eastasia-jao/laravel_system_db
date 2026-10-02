<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('fully_booked_orders')
            ->whereNotNull('pulled_out_at')
            ->orderBy('id')
            ->each(function ($order) {
                $saleId = DB::table('sales_transactions')
                    ->where('store_hub_id', $order->store_hub_id)
                    ->where('channel_type', 'fully_booked')
                    ->where('order_number', $order->order_number)
                    ->value('id');

                if (! $saleId) {
                    $saleId = DB::table('sales_transactions')->insertGetId([
                        'store_hub_id' => $order->store_hub_id,
                        'channel_type' => 'fully_booked',
                        'order_date' => substr((string) $order->pulled_out_at, 0, 10),
                        'order_number' => $order->order_number,
                        'customer_name' => 'Fully Booked',
                        'status' => 'confirmed',
                        'user_id' => $order->submitted_by,
                        'created_at' => $order->pulled_out_at,
                        'updated_at' => $order->pulled_out_at,
                    ]);
                }

                $existingProductIds = DB::table('transaction_items')
                    ->where('transaction_id', $saleId)
                    ->pluck('product_id');
                $items = DB::table('fully_booked_order_items')
                    ->where('fully_booked_order_id', $order->id)
                    ->whereNotIn('product_id', $existingProductIds)
                    ->get();

                foreach ($items as $item) {
                    DB::table('transaction_items')->insert([
                        'transaction_id' => $saleId,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit_price' => 0,
                        'discount_percentage' => 0,
                        'line_total' => 0,
                        'created_at' => $order->pulled_out_at,
                        'updated_at' => $order->pulled_out_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Backfilled order records are retained because returns may reference them.
    }
};
