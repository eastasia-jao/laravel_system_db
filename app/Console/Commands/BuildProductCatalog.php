<?php

namespace App\Console\Commands;

use App\Models\CatalogProduct;
use App\Models\Product;
use App\Models\StoreHub;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildProductCatalog extends Command
{
    protected $signature = 'inventory:build-catalog';

    protected $description = 'Link existing branch stock rows to a shared catalog without changing stock, prices or history';

    public function handle(): int
    {
        if (! Schema::hasColumn('products', 'item_id')) {
            $unlinked = DB::table('products')->whereNull('catalog_product_id')->count();
            $this->info("Catalog is normalized. Unlinked branch inventory records: $unlinked.");

            return $unlinked ? self::FAILURE : self::SUCCESS;
        }
        // Process head office first to select the default product descriptions.
        $hubs = StoreHub::orderByDesc('is_head_office')->orderBy('id')->pluck('id');
        foreach ($hubs as $hubId) {
            Product::where('store_hub_id', $hubId)->whereNull('catalog_product_id')->chunkById(200, function ($products) {
                DB::transaction(function () use ($products) {
                    foreach ($products as $product) {
                        $catalog = CatalogProduct::firstOrCreate(['item_id' => $product->item_id], $product->only(CatalogProduct::FIELDS));
                        DB::table('products')->where('id', $product->id)->whereNull('catalog_product_id')->update(['catalog_product_id' => $catalog->id]);
                    }
                });
            });
        }
        $this->info('Catalog ready. Branch stock, prices, product IDs and historical records were preserved.');

        return self::SUCCESS;
    }
}
