<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $with = ['catalogProduct'];

    protected $hidden = ['catalogProduct'];

    private array $catalogChanges = [];

    public bool $preserveCatalogMetadata = false;

    public bool $updateExistingCatalog = false;

    protected $fillable = [
        'catalog_product_id',
        'item_id',
        'name',
        'description',
        'barcode',
        'brand',
        'retail_group',
        'retail_department',
        'unit_type',
        'cost_price',
        'sales_price',
        'wholesale_price',
        'shopee_price',
        'lazada_price',
        'tiktok_price',
        'stock',
        'status',
        'store_hub_id',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'sales_price' => 'decimal:2',
        'wholesale_price' => 'decimal:2',
        'shopee_price' => 'decimal:2',
        'lazada_price' => 'decimal:2',
        'tiktok_price' => 'decimal:2',
    ];

    public function storeHub()
    {
        return $this->belongsTo(StoreHub::class, 'store_hub_id');
    }

    public function getAttribute($key)
    {
        if (in_array($key, CatalogProduct::FIELDS, true)) {
            return array_key_exists($key, $this->catalogChanges)
                ? $this->catalogChanges[$key]
                : ($this->catalog_product_id ? $this->catalogProduct?->getAttribute($key) : null);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, CatalogProduct::FIELDS, true)) {
            $this->catalogChanges[$key] = $value;

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    public function attributesToArray()
    {
        return [...parent::attributesToArray(), ...$this->only(CatalogProduct::FIELDS)];
    }

    public function save(array $options = [])
    {
        return DB::transaction(function () use ($options) {
            if ($this->catalogChanges || ! $this->catalog_product_id) {
                $itemId = $this->catalogChanges['item_id'] ?? $this->catalogProduct?->item_id;
                if ($itemId === null || $itemId === '') {
                    throw new \InvalidArgumentException('A product Item ID is required.');
                }
                $catalog = $this->exists && $this->catalog_product_id
                    ? CatalogProduct::findOrFail($this->catalog_product_id)
                    : CatalogProduct::firstOrCreate(['item_id' => $itemId], $this->catalogChanges);
                // Reusing a catalog entry for a new branch must never overwrite its master data.
                if (($this->exists || $this->updateExistingCatalog) && ! $this->preserveCatalogMetadata) {
                    $catalog->fill($this->catalogChanges)->save();
                }
                $this->catalog_product_id = $catalog->id;
                $this->setRelation('catalogProduct', $catalog);
            }
            $saved = parent::save($options);
            if ($saved) {
                $this->catalogChanges = [];
            }

            return $saved;
        });
    }

    public function scopeWhereCatalog($query, string $field, $operator, $value = null)
    {
        if (func_num_args() === 3) {
            $value = $operator;
            $operator = '=';
        }

        return $query->whereHas('catalogProduct', fn ($catalog) => $catalog->where($field, $operator, $value));
    }

    public function scopeOrderByCatalog($query, string $field = 'name')
    {
        if ($field === 'item_id') {
            return $query
                ->orderByRaw('(SELECT CAST(item_id AS UNSIGNED) FROM catalog_products WHERE catalog_products.id = products.catalog_product_id)')
                ->orderBy(CatalogProduct::select($field)->whereColumn('catalog_products.id', 'products.catalog_product_id'))
                ->orderBy('products.id');
        }

        return $query->orderBy(CatalogProduct::select($field)->whereColumn('catalog_products.id', 'products.catalog_product_id'))->orderBy('products.id');
    }

    public static function catalogOptions(int $hubId, string $field)
    {
        return CatalogProduct::whereHas('branchInventories', fn ($q) => $q->where('store_hub_id', $hubId))
            ->whereNotNull($field)->distinct()->orderBy($field)->pluck($field);
    }

    public function catalogProduct()
    {
        return $this->belongsTo(CatalogProduct::class);
    }

    public function stockAllocation()
    {
        return $this->hasOne(ProductStockAllocation::class);
    }

    public function wholesaleAvailableStock(bool $fallbackToPhysical = true): int
    {
        return $this->channelAvailableStock('wholesale', $fallbackToPhysical);
    }

    public function channelAvailableStock(string $channel, bool $fallbackToPhysical = true): int
    {
        $allocated = $this->stockAllocation?->{$channel};
        if ($allocated === null) {
            return $fallbackToPhysical ? (int) $this->stock : 0;
        }
        return max(0, (int) $allocated);
    }

    public function unallocatedStock(): int
    {
        $allocation = $this->stockAllocation;
        $allocated = collect(['online', 'wholesale', 'shopee', 'lazada', 'tiktok'])
            ->sum(fn ($channel) => (int) ($allocation?->{$channel} ?? 0));

        return max(0, (int) $this->stock - $allocated);
    }

    public function inventoryTransactions()
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
