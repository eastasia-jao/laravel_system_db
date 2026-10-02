<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('inventory_transactions', 'product_replacement_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->foreignId('product_replacement_id')
                    ->nullable()
                    ->after('transaction_item_id')
                    ->constrained('product_replacements')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_transactions', 'product_replacement_id')) {
            Schema::table('inventory_transactions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('product_replacement_id');
            });
        }
    }
};
