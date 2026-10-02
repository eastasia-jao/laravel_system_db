<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $shared = ['item_id', 'name', 'description', 'barcode', 'brand', 'retail_group', 'retail_department', 'unit_type'];

    public function up(): void
    {
        // Refuse to discard unresolved metadata. Backfill using SQL, independent of application models.
        DB::table('products')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $catalog = DB::table('catalog_products')->where('item_id', $row->item_id)->first();
                if (! $catalog) {
                    $values = array_intersect_key((array) $row, array_flip($this->shared));
                    $id = DB::table('catalog_products')->insertGetId([...$values, 'created_at' => now(), 'updated_at' => now()]);
                    $catalog = DB::table('catalog_products')->where('id', $id)->first();
                }
                foreach ($this->shared as $field) {
                    if ((string) $row->$field !== (string) $catalog->$field) {
                        throw new RuntimeException("Catalog mismatch for branch product {$row->id}, field {$field}. Resolve it before normalization.");
                    }
                }
                DB::table('products')->where('id', $row->id)->update(['catalog_product_id' => $catalog->id]);
            }
        });
        foreach (Schema::getIndexes('products') as $index) {
            if (array_intersect($index['columns'], $this->shared)) {
                Schema::table('products', function (Blueprint $table) use ($index) {
                    // PostgreSQL requires its UNIQUE constraint to be dropped
                    // as a constraint instead of dropping the backing index.
                    if ($index['name'] === 'products_item_id_store_hub_id_unique') {
                        $table->dropUnique($index['name']);

                        return;
                    }

                    $table->dropIndex($index['name']);
                });
            }
        }
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn($this->shared);
            $table->unsignedBigInteger('catalog_product_id')->nullable(false)->change();
            $table->unique(['store_hub_id', 'catalog_product_id'], 'products_branch_catalog_unique');
            $table->index(['store_hub_id', 'status', 'stock'], 'products_branch_stock_idx');
        });
        Schema::table('catalog_products', fn (Blueprint $table) => $table->index('name', 'catalog_products_name_index'));
        Schema::table('sales_transactions', fn (Blueprint $table) => $table->index(['store_hub_id', 'status', 'order_date'], 'sales_branch_status_date_idx'));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach ($this->shared as $field) {
                if ($field === 'description') {
                    $table->text($field)->nullable();
                } else {
                    $table->string($field)->nullable();
                }
            }
            $table->dropUnique('products_branch_catalog_unique');
            $table->dropIndex('products_branch_stock_idx');
            $table->unsignedBigInteger('catalog_product_id')->nullable()->change();
        });
        DB::table('products')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $catalog = DB::table('catalog_products')->where('id', $row->catalog_product_id)->first();
                DB::table('products')->where('id', $row->id)->update(array_intersect_key((array) $catalog, array_flip($this->shared)));
            }
        });
        Schema::table('products', function (Blueprint $table) {
            $table->unique(['item_id', 'store_hub_id']);
            $table->index('name');
            $table->index('barcode');
            $table->index(['store_hub_id', 'name'], 'products_hub_name_idx');
        });
        Schema::table('catalog_products', fn (Blueprint $table) => $table->dropIndex('catalog_products_name_index'));
        Schema::table('sales_transactions', fn (Blueprint $table) => $table->dropIndex('sales_branch_status_date_idx'));
    }
};
