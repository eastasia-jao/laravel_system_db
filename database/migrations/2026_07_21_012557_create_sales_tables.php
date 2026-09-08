<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notice: The old 'sales' table schema has been completely removed from here!

        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_hub_id');
            $table->string('channel_type');
            $table->date('order_date');
            $table->string('order_number');
            $table->string('customer_name');
            $table->string('contact_number')->nullable();
            $table->text('address')->nullable();

            // Channel Specific Meta
            $table->date('date_of_arrangement')->nullable();
            $table->string('location')->nullable();
            $table->string('mode_of_payment')->nullable();
            $table->string('custom_mop')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('custom_bank_name')->nullable();
            $table->string('check_number')->nullable();
            $table->date('check_date')->nullable();
            $table->string('proof_of_payment')->nullable();

            // Fees & Discounts
            $table->string('shipping_fee_type')->nullable();
            $table->decimal('shipping_fee_amount', 10, 2)->default(0);
            $table->decimal('shipping_service_fee', 10, 2)->default(0);
            $table->decimal('sales_after_transaction_fee', 10, 2)->nullable();
            $table->decimal('additional_discount_percentage', 5, 2)->default(0);

            // Withholding Tax Columns
            $table->decimal('withholding_tax', 5, 2)->nullable()->default(0);
            $table->decimal('withholding_tax_amount', 10, 2)->nullable()->default(0);

            $table->text('note')->nullable();

            // Totals
            $table->decimal('sub_total', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('sales_transactions')->onDelete('cascade');
            $table->unsignedBigInteger('product_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('sales_transactions');
    }
};
