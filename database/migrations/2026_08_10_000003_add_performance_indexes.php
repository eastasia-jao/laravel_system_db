<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ProductController::index/searchAjax filter on these columns constantly
 * (LIKE queries on name/barcode/brand/etc, status toggles), and the
 * products table already has 13,000+ rows in the live dump, so these
 * were full table scans. Also indexes order_date, which the sales report
 * added in this batch of fixes sorts/filters by.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! $this->indexExists('products', 'products_status_index')) {
                $table->index('status');
            }
            if (! $this->indexExists('products', 'products_name_index')) {
                $table->index('name');
            }
            if (! $this->indexExists('products', 'products_barcode_index')) {
                $table->index('barcode');
            }
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            if (! $this->indexExists('sales_transactions', 'sales_transactions_order_date_index')) {
                $table->index('order_date');
            }
        });

        Schema::table('pending_sales', function (Blueprint $table) {
            if (! $this->indexExists('pending_sales', 'pending_sales_status_index')) {
                $table->index('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_status_index');
            $table->dropIndex('products_name_index');
            $table->dropIndex('products_barcode_index');
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropIndex('sales_transactions_order_date_index');
        });

        Schema::table('pending_sales', function (Blueprint $table) {
            $table->dropIndex('pending_sales_status_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return Schema::hasIndex($table, $indexName);
    }
};
