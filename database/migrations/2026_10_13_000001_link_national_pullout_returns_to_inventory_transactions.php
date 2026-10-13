<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->change();
            $table->foreignId('national_pullout_id')->nullable()->after('transaction_item_id')
                ->constrained('national_pullouts')->nullOnDelete();
            $table->foreignId('national_pullout_item_id')->nullable()->after('national_pullout_id')
                ->constrained('national_pullout_items')->nullOnDelete();
            $table->foreignId('national_product_id')->nullable()->after('national_pullout_item_id')
                ->constrained('national_products')->nullOnDelete();
            $table->index(
                ['national_pullout_id', 'national_pullout_item_id'],
                'inventory_return_national_pullout_item_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('inventory_return_national_pullout_item_idx');
            $table->dropConstrainedForeignId('national_product_id');
            $table->dropConstrainedForeignId('national_pullout_item_id');
            $table->dropConstrainedForeignId('national_pullout_id');
            $table->foreignId('product_id')->nullable(false)->change();
        });
    }
};
