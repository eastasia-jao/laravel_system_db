<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_hub_id');
            $table->string('sales_channel'); // e.g., Shopee, Lazada, TikTok, Online Orders
            $table->date('placed_order_date');
            $table->string('customer_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('mode_of_payment')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('withholding_tax', 5, 2)->nullable()->default(0);
            $table->decimal('withholding_tax_amount', 10, 2)->nullable()->default(0);
            $table->string('courier')->nullable();
            $table->string('packed')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('inventory_status')->nullable();
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->json('items'); // Stores the cart items as JSON data
            $table->string('status')->default('pending'); // pending, confirmed, cancelled
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_sales');
    }
};
