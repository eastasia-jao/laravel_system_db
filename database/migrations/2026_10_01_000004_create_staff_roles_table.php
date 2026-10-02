<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug')->unique();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        $now = now();
        $builtInRoles = [
            'admin' => 'Admin',
            'inventory_staff' => 'Inventory Staff',
            'sales_associate' => 'Sales Associate',
            'sales_marketing_staff' => 'Sales/Marketing Staff',
        ];

        foreach ($builtInRoles as $slug => $name) {
            DB::table('staff_roles')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('users')
            ->whereNotNull('role')
            ->where('role', '<>', '')
            ->distinct()
            ->pluck('role')
            ->reject(fn (string $slug) => isset($builtInRoles[$slug]))
            ->each(function (string $slug) use ($now): void {
                DB::table('staff_roles')->insertOrIgnore([
                    'name' => Str::headline($slug),
                    'slug' => $slug,
                    'is_system' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
    }
};
