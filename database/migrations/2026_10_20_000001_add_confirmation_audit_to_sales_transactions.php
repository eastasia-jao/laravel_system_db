<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
        });

        DB::table('pending_sales')
            ->where('status', 'confirmed')
            ->whereNotNull('confirmed_by')
            ->whereNotNull('confirmed_at')
            ->orderBy('id')
            ->chunk(500, function ($pendingSales) {
                foreach ($pendingSales as $pendingSale) {
                    if (! $pendingSale->invoice_number) {
                        continue;
                    }

                    DB::table('sales_transactions')
                        ->where('store_hub_id', $pendingSale->store_hub_id)
                        ->where('order_number', $pendingSale->invoice_number)
                        ->whereNull('confirmed_by')
                        ->update([
                            'confirmed_by' => $pendingSale->confirmed_by,
                            'confirmed_at' => $pendingSale->confirmed_at,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropColumn('confirmed_at');
        });
    }
};
