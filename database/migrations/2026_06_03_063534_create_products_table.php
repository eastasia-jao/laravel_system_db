<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->string('retail_group')->nullable();
            $table->string('retail_department')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('sales_price', 12, 2)->nullable();
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->decimal('shopee_price', 12, 2)->nullable();
            $table->decimal('lazada_price', 12, 2)->nullable();
            $table->decimal('tiktok_price', 12, 2)->nullable();
            $table->foreignId('store_hub_id')->constrained('store_hubs')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
