<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fully_booked_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fully_booked_order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type', 100);
            $table->timestamps();
            $table->index(['fully_booked_order_id', 'id']);
        });

        DB::table('fully_booked_orders')->orderBy('id')->each(function (object $order): void {
            DB::table('fully_booked_order_attachments')->insert([
                'fully_booked_order_id' => $order->id,
                'path' => $order->attachment_path,
                'original_filename' => $order->original_filename,
                'mime_type' => $order->mime_type,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fully_booked_order_attachments');
    }
};
