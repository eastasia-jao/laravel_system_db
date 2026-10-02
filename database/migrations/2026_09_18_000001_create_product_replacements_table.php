<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('sales_transactions')->cascadeOnDelete();
            $table->foreignId('transaction_item_id')->constrained('transaction_items')->cascadeOnDelete();
            $table->unsignedBigInteger('original_product_id');
            $table->unsignedBigInteger('replacement_product_id');
            $table->unsignedInteger('quantity');
            $table->decimal('original_unit_price', 12, 2)->default(0);
            $table->decimal('replacement_unit_price', 12, 2)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['transaction_item_id', 'created_at']);
            $table->index('original_product_id');
            $table->index('replacement_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_replacements');
    }
};
