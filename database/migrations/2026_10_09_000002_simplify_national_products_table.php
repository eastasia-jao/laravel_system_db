<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->dropIndex(['status']);
        });

        Schema::table('national_products', function (Blueprint $table) {
            $table->dropColumn([
                'retail_group',
                'retail_department',
                'cost_price',
                'sales_price',
                'wholesale_price',
                'shopee_price',
                'lazada_price',
                'tiktok_price',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('national_products', function (Blueprint $table) {
            $table->string('retail_group')->nullable();
            $table->string('retail_department')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('sales_price', 12, 2)->nullable();
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->decimal('shopee_price', 12, 2)->nullable();
            $table->decimal('lazada_price', 12, 2)->nullable();
            $table->decimal('tiktok_price', 12, 2)->nullable();
            $table->string('status')->default('active')->index();
        });
    }
};
