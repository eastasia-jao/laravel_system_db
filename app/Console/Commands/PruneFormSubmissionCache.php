<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneFormSubmissionCache extends Command
{
    protected $signature = 'form-submissions:prune';

    protected $description = 'Remove expired duplicate form submission records from the database cache';

    public function handle(): int
    {
        if (config('cache.default') !== 'database') {
            $this->info('The database cache store is not active; no form submission records need pruning.');

            return self::SUCCESS;
        }

        $store = config('cache.stores.database');
        $connection = $store['connection'] ?? null;
        $table = $store['table'] ?? 'cache';
        $expired = DB::connection($connection)
            ->table($table)
            ->where('key', 'like', 'form-submission:%')
            ->where('expiration', '<=', now()->timestamp);
        $removed = $expired->delete();

        $this->info("Removed {$removed} expired form submission records.");

        return self::SUCCESS;
    }
}
