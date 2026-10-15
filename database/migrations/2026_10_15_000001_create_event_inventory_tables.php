<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_pullouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('store_hub_id')->constrained('store_hubs');
            $table->string('source');
            $table->date('occurred_on')->index();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['store_hub_id', 'status', 'occurred_on'], 'event_pullouts_hub_status_date_idx');
        });

        Schema::create('event_pullout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_pullout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('stock_condition', 20)->default('good');
            $table->unsignedInteger('requested_quantity')->nullable();
            $table->unsignedInteger('quantity');
            $table->timestamps();

            $table->unique(
                ['event_pullout_id', 'product_id', 'stock_condition'],
                'event_pullout_product_condition_unique'
            );
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->foreignId('event_pullout_id')->nullable()->after('store_hub_id')
                ->constrained('event_pullouts')->nullOnDelete();
            $table->index(['event_pullout_id', 'order_date'], 'sales_event_date_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('event_pullout_id')->nullable()->after('transaction_item_id')
                ->constrained('event_pullouts')->nullOnDelete();
            $table->foreignId('event_pullout_item_id')->nullable()->after('event_pullout_id')
                ->constrained('event_pullout_items')->nullOnDelete();
            $table->index(['event_pullout_id', 'type'], 'inventory_event_type_idx');
        });

        $legacyPullouts = DB::table('inventory_transactions')
            ->where('type', 'sponsor_workshop')
            ->where(function ($query) {
                $query->whereNull('channel')->orWhere('channel', '!=', 'fully_booked');
            })
            ->whereNotNull('reference')
            ->orderBy('id')
            ->get()
            ->groupBy('reference');

        foreach ($legacyPullouts as $reference => $rows) {
            $first = $rows->first();
            $eventId = DB::table('event_pullouts')->insertGetId([
                'reference' => $reference,
                'store_hub_id' => $first->store_hub_id,
                'source' => $first->source ?: 'Event',
                'occurred_on' => $first->occurred_on,
                'notes' => $first->notes,
                'status' => 'open',
                'created_by' => $first->created_by,
                'created_at' => $first->created_at,
                'updated_at' => $first->updated_at,
            ]);

            foreach ($rows->groupBy(fn ($row) => $row->product_id.':'.($row->condition ?: 'good')) as $conditionRows) {
                $line = $conditionRows->first();
                $condition = $line->condition ?: 'good';
                $itemId = DB::table('event_pullout_items')->insertGetId([
                    'event_pullout_id' => $eventId,
                    'product_id' => $line->product_id,
                    'stock_condition' => $condition,
                    'requested_quantity' => $conditionRows->sum(fn ($row) => (int) ($row->pullout_qty ?? 0)) ?: null,
                    'quantity' => $conditionRows->sum(fn ($row) => (int) $row->quantity),
                    'created_at' => $line->created_at,
                    'updated_at' => $line->updated_at,
                ]);

                DB::table('inventory_transactions')
                    ->whereIn('id', $conditionRows->pluck('id'))
                    ->update([
                        'event_pullout_id' => $eventId,
                        'event_pullout_item_id' => $itemId,
                    ]);
            }

            DB::table('inventory_transactions')
                ->where('type', 'return')
                ->where('channel', 'event')
                ->where('store_hub_id', $first->store_hub_id)
                ->where('reference', $reference)
                ->update(['event_pullout_id' => $eventId]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_event_type_idx');
            $table->dropConstrainedForeignId('event_pullout_item_id');
            $table->dropConstrainedForeignId('event_pullout_id');
        });
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropIndex('sales_event_date_idx');
            $table->dropConstrainedForeignId('event_pullout_id');
        });
        Schema::dropIfExists('event_pullout_items');
        Schema::dropIfExists('event_pullouts');
    }
};
