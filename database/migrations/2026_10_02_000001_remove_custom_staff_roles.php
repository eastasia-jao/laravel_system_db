<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staff_roles')) {
            return;
        }

        $customRoleSlugs = DB::table('staff_roles')
            ->where('is_system', false)
            ->pluck('slug');

        if ($customRoleSlugs->isNotEmpty()) {
            DB::table('users')
                ->whereIn('role', $customRoleSlugs)
                ->update(['role' => 'sales_associate']);

            DB::table('staff_roles')->where('is_system', false)->delete();
        }

        if (Schema::hasColumn('staff_roles', 'permissions')) {
            Schema::table('staff_roles', function (Blueprint $table) {
                $table->dropColumn('permissions');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('staff_roles') || Schema::hasColumn('staff_roles', 'permissions')) {
            return;
        }

        Schema::table('staff_roles', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('is_system');
        });
    }
};
