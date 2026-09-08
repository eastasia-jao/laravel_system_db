<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('online')->default(0);
            $table->unsignedInteger('wholesale')->default(0);
            $table->unsignedInteger('shopee')->default(0);
            $table->unsignedInteger('lazada')->default(0);
            $table->unsignedInteger('tiktok')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_allocations');
    }
};