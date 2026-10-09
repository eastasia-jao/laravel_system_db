<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('national_pullouts', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->date('occurred_on');
            $table->text('remarks')->nullable();
            $table->foreignId('store_hub_id')->constrained('store_hubs')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['occurred_on', 'store_hub_id']);
        });

        Schema::create('national_pullout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('national_pullout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('national_product_id')->nullable()->constrained('national_products')->nullOnDelete();
            $table->string('item_id');
            $table->string('product_name');
            $table->unsignedInteger('quantity');
            $table->string('unit_type')->nullable();
            $table->string('purpose');
            $table->unsignedInteger('physical_stock');
            $table->unsignedInteger('actual_pullout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('national_pullout_items');
        Schema::dropIfExists('national_pullouts');
    }
};
