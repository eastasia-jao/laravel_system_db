<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->unsignedInteger('returned_quantity')->default(0);
            $table->string('return_status')->default('none');
            $table->decimal('customer_refund_amount', 12, 2)->default(0);
            $table->dateTime('returned_at')->nullable();
            $table->text('return_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn([
                'returned_quantity',
                'return_status',
                'customer_refund_amount',
                'returned_at',
                'return_reason',
            ]);
        });
    }
};
