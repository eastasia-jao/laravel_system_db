<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Drops the global unique restriction on item_id
            $table->dropUnique('products_item_id_unique');

            // Makes the item_id unique *only* within the same store hub
            $table->unique(['item_id', 'store_hub_id']);
        });
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['item_id', 'store_hub_id']);
            $table->unique('item_id');
        });
    }
};
