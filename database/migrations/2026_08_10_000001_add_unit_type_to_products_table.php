<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The live database (see laravel_inventorydb.sql) already has a
 * `unit_type` column on `products`, and the app reads/writes it
 * everywhere (Product::$fillable, ProductController, CSV import/export).
 * No migration ever created it, though — it must have been added by
 * hand directly on the database. Anyone running `php artisan migrate`
 * on a fresh database would get an "Unknown column 'unit_type'" SQL
 * error the first time a product is saved. This migration closes that
 * gap so migrations reproduce the real schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit_type')) {
                $table->string('unit_type')->nullable()->after('brand');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'unit_type')) {
                $table->dropColumn('unit_type');
            }
        });
    }
};
