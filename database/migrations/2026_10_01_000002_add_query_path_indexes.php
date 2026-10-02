<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'transaction_items' => [
            'transaction_items_transaction_id_idx' => ['transaction_id'],
        ],
        'sales_payment_records' => [
            'sales_payment_records_transaction_id_idx' => ['sales_transaction_id'],
        ],
        'product_replacements' => [
            'product_replacements_transaction_id_idx' => ['transaction_id'],
        ],
        'sales_transactions' => [
            'sales_transactions_hub_order_date_id_idx' => ['store_hub_id', 'order_date', 'id'],
            'sales_transactions_hub_arrangement_date_idx' => ['store_hub_id', 'date_of_arrangement'],
        ],
        'pending_sales' => [
            'pending_sales_hub_status_created_idx' => ['store_hub_id', 'status', 'created_at'],
        ],
        'staff_activity_logs' => [
            'staff_activity_logs_user_created_idx' => ['user_id', 'created_at'],
        ],
        'inventory_transactions' => [
            'inventory_transactions_hub_occurred_id_idx' => ['store_hub_id', 'occurred_on', 'id'],
            'inventory_transactions_replacement_id_idx' => ['product_replacement_id'],
        ],
        'fully_booked_orders' => [
            'fully_booked_orders_hub_created_idx' => ['store_hub_id', 'created_at'],
        ],
        'fully_booked_order_items' => [
            'fully_booked_items_order_id_idx' => ['fully_booked_order_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            $toAdd = [];
            foreach ($indexes as $name => $columns) {
                if (! $this->hasLeadingIndex($tableName, $columns)) {
                    $toAdd[$name] = $columns;
                }
            }

            if ($toAdd !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($toAdd) {
                    foreach ($toAdd as $name => $columns) {
                        $table->index($columns, $name);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $tableName => $indexes) {
            $toDrop = array_filter(array_keys($indexes), fn (string $name) => Schema::hasIndex($tableName, $name));
            if ($toDrop !== []) {
                Schema::table($tableName, function (Blueprint $table) use ($toDrop) {
                    foreach ($toDrop as $name) {
                        $table->dropIndex($name);
                    }
                });
            }
        }
    }

    private function hasLeadingIndex(string $tableName, array $columns): bool
    {
        foreach (Schema::getIndexes($tableName) as $index) {
            if (array_slice($index['columns'], 0, count($columns)) === $columns) {
                return true;
            }
        }

        return false;
    }
};
