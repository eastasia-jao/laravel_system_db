<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Root-cause fix for products that show up with a blank name throughout
 * the app (e.g. the pending-sales "Review & Confirm" modal). The CSV
 * importer (see ProductController::importCsv) used to save whatever was
 * in the source CSV's name column with no fallback; for a large chunk of
 * the real import file that column was blank while the actual product
 * text was in the description column instead. ~5,300 of ~13,170 products
 * were affected. The importer itself is fixed separately for future
 * imports — this migration backfills the products already saved with a
 * blank name.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where(function ($query) {
                $query->whereNull('name')->orWhere('name', '');
            })
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->update([
                'name' => DB::raw('description'),
            ]);
    }

    public function down(): void
    {
        // Not reversible — we don't know which rows were originally
        // blank vs. genuinely equal to their description.
    }
};
