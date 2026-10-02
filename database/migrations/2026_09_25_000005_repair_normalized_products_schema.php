<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $legacyColumns = [
        'item_id',
        'name',
        'description',
        'barcode',
        'brand',
        'retail_group',
        'retail_department',
        'unit_type',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('products', 'catalog_product_id')) {
            DB::table('products')
                ->whereNull('catalog_product_id')
                ->orderBy('id')
                ->chunkById(200, function ($products) {
                    foreach ($products as $product) {
                        $catalogId = DB::table('catalog_products')
                            ->where('item_id', $product->item_id ?? null)
                            ->value('id');

                        if ($catalogId) {
                            DB::table('products')
                                ->where('id', $product->id)
                                ->update(['catalog_product_id' => $catalogId]);
                        }
                    }
                });
        }

        foreach (Schema::getIndexes('products') as $index) {
            if ($index['name'] === 'products_item_id_store_hub_id_unique'
                || array_intersect($index['columns'], $this->legacyColumns)) {
                Schema::table('products', function (Blueprint $table) use ($index) {
                    // PostgreSQL owns the backing index of a UNIQUE constraint.
                    // Drop the constraint, not its index, so a fresh Postgres
                    // database can continue through the normalization migration.
                    if ($index['unique']) {
                        $table->dropUnique($index['name']);

                        return;
                    }

                    $table->dropIndex($index['name']);
                });
            }
        }

        $columns = array_values(array_filter(
            $this->legacyColumns,
            fn (string $column) => Schema::hasColumn('products', $column)
        ));
        if ($columns) {
            Schema::table('products', fn (Blueprint $table) => $table->dropColumn($columns));
        }

        if (Schema::hasColumn('products', 'catalog_product_id')
            && ! collect(Schema::getIndexes('products'))->contains(fn (array $index) => $index['name'] === 'products_branch_catalog_unique')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['store_hub_id', 'catalog_product_id'], 'products_branch_catalog_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep the normalized schema on rollback; the legacy columns are not recoverable safely.
    }
};
