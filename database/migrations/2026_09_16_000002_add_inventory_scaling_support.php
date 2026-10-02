<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('item_id')->unique();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('barcode')->nullable()->index();
            foreach (['brand', 'retail_group', 'retail_department', 'unit_type'] as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('catalog_product_id')->nullable()->constrained('catalog_products');
            $table->index(['store_hub_id', 'name'], 'products_hub_name_idx');
            $table->index(['store_hub_id', 'status', 'id'], 'products_hub_status_id_idx');
        });
        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->string('processing_status')->nullable();
            $table->text('processing_error')->nullable();
            $table->index(['submitted_by', 'store_hub_id', 'id'], 'file_requests_owner_hub_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_file_requests', function (Blueprint $table) {
            $table->dropIndex('file_requests_owner_hub_idx');
            $table->dropColumn(['processing_status', 'processing_error']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_hub_name_idx');
            $table->dropIndex('products_hub_status_id_idx');
            $table->dropConstrainedForeignId('catalog_product_id');
        });
        Schema::dropIfExists('catalog_products');
    }
};
