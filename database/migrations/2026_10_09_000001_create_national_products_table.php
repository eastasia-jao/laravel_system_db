<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_products', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->string('retail_group')->nullable();
            $table->string('retail_department')->nullable();
            $table->string('unit_type')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('sales_price', 12, 2)->nullable();
            $table->decimal('wholesale_price', 12, 2)->nullable();
            $table->decimal('shopee_price', 12, 2)->nullable();
            $table->decimal('lazada_price', 12, 2)->nullable();
            $table->decimal('tiktok_price', 12, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_products');
    }
};
