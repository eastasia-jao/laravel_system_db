<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InventoryHealth extends Command
{
    protected $signature = 'inventory:health';

    protected $description = 'Read inventory size, catalog integrity and background queue health';

    public function handle(): int
    {
        $jobs = DB::table('jobs')->where('queue', 'product-files');
        $now = time();
        $oldest = (clone $jobs)->whereNull('reserved_at')->min('created_at');
        $staleReserved = (clone $jobs)->whereNotNull('reserved_at')->where('reserved_at', '<', $now - 900)->count();
        $orphans = DB::table('products')->leftJoin('catalog_products', 'catalog_products.id', '=', 'products.catalog_product_id')->whereNull('catalog_products.id')->count();
        $this->table(['Measure', 'Value'], [
            ['Shared products', DB::table('catalog_products')->count()],
            ['Branch inventory records', DB::table('products')->count()],
            ['Branches', DB::table('store_hubs')->count()],
            ['Missing catalog links', $orphans],
            ['Waiting jobs', (clone $jobs)->whereNull('reserved_at')->count()],
            ['Reserved jobs', (clone $jobs)->whereNotNull('reserved_at')->count()],
            ['Stale reserved jobs (>15 minutes)', $staleReserved],
            ['Oldest waiting job (seconds)', $oldest ? max(0, $now - $oldest) : 0],
            ['Failed queue jobs', DB::table('failed_jobs')->count()],
            ['Failed file requests', DB::table('product_file_requests')->where('processing_status', 'failed')->count()],
        ]);
        if ($oldest && $now - $oldest > 300) {
            $this->warn('Jobs have waited over five minutes. Check whether the inventory worker is running or busy.');
        }
        if ($staleReserved) {
            $this->error('At least one inventory job has exceeded the 15-minute reservation window. Inspect worker and failed-job logs before retrying.');
        }

        return $orphans || $staleReserved ? self::FAILURE : self::SUCCESS;
    }
}
