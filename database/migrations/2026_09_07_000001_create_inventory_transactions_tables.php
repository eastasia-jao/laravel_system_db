<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->index();
            $table->string('type')->index();
            $table->foreignId('store_hub_id')->constrained('store_hubs');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('source_hub_id')->nullable()->constrained('store_hubs');
            $table->foreignId('target_hub_id')->nullable()->constrained('store_hubs');
            $table->string('channel')->nullable()->index();
            $table->string('source')->nullable();
            $table->string('condition')->nullable();
            $table->unsignedInteger('quantity');
            $table->date('occurred_on')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_hub_id', 'type', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
