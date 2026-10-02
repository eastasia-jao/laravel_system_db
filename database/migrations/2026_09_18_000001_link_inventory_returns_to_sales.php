<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_transactions', 'sales_transaction_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->foreignId('sales_transaction_id')
                    ->nullable()
                    ->after('reference')
                    ->constrained('sales_transactions')
                    ->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('inventory_transactions', 'transaction_item_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->foreignId('transaction_item_id')
                    ->nullable()
                    ->after('sales_transaction_id')
                    ->constrained('transaction_items')
                    ->nullOnDelete();
            });
        }
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['sales_transaction_id', 'transaction_item_id'], 'inventory_return_sale_item_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_return_sale_item_idx');
            $table->dropConstrainedForeignId('transaction_item_id');
            $table->dropConstrainedForeignId('sales_transaction_id');
        });
    }
};
