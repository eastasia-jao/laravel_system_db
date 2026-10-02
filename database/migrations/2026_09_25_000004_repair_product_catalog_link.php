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
        if (! Schema::hasTable('catalog_products')) {
            Schema::create('catalog_products', function (Blueprint $table) {
                $table->id();
                $table->string('item_id')->unique();
                $table->string('name')->nullable();
                $table->text('description')->nullable();
                $table->string('barcode')->nullable()->index();
                $table->string('brand')->nullable();
                $table->string('retail_group')->nullable();
                $table->string('retail_department')->nullable();
                $table->string('unit_type')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('products', 'catalog_product_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('catalog_product_id')->nullable()->after('id')->constrained('catalog_products');
            });
        }

        if (! Schema::hasColumn('products', 'item_id')) {
            return;
        }

        DB::table('products')->whereNull('catalog_product_id')->orderBy('id')->chunkById(200, function ($products) {
            foreach ($products as $product) {
                $itemId = trim((string) ($product->item_id ?? ''));
                if ($itemId === '') {
                    continue;
                }

                $catalogId = DB::table('catalog_products')->where('item_id', $itemId)->value('id');
                if (! $catalogId) {
                    $values = [];
                    foreach ($this->shared as $field) {
                        if (property_exists($product, $field)) {
                            $values[$field] = $product->{$field};
                        }
                    }
                    $catalogId = DB::table('catalog_products')->insertGetId([
                        ...$values,
                        'item_id' => $itemId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('products')->where('id', $product->id)->update(['catalog_product_id' => $catalogId]);
            }
        });
    }

    public function down(): void
    {
        // This is a data-repair migration. Keep the restored relationship on rollback.
    }
};
