<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hub_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_id');
            $table->string('order_number')->unique();
            $table->string('channel_type');
            $table->string('customer_name')->nullable();
            $table->decimal('grand_total', 12, 2);
            $table->date('order_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hub_transactions');
    }
};
