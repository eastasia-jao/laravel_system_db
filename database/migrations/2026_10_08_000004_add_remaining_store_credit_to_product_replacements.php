<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_replacements', function (Blueprint $table) {
            if (! Schema::hasColumn('product_replacements', 'remaining_store_credit')) {
                $table->decimal('remaining_store_credit', 12, 2)->default(0)->after('exchange_credit');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_replacements', 'remaining_store_credit')) {
            Schema::table('product_replacements', fn (Blueprint $table) => $table->dropColumn('remaining_store_credit'));
        }
    }
};
