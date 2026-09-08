<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several relational columns were created as plain unsignedBigInteger
 * columns with no actual FOREIGN KEY constraint, so the database never
 * enforced referential integrity for them:
 *   - sales_transactions.store_hub_id -> store_hubs.id
 *   - transaction_items.product_id    -> products.id
 *   - pending_sales.store_hub_id      -> store_hubs.id
 *   - pending_sales.submitted_by      -> users.id (nullable)
 *   - pending_sales.confirmed_by      -> users.id (nullable)
 *   - users.hub_id                    -> store_hubs.id (nullable)
 *
 * Checked the existing data dump for orphaned rows before writing this
 * (none found), but each block is still wrapped defensively so a
 * constraint that can't be added on someone's copy of the data doesn't
 * abort the whole migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->safeForeignKey('sales_transactions', function (Blueprint $table) {
            $table->foreign('store_hub_id')->references('id')->on('store_hubs')->cascadeOnDelete();
        }, 'sales_transactions_store_hub_id_foreign');

        $this->safeForeignKey('transaction_items', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->restrictOnDelete();
        }, 'transaction_items_product_id_foreign');

        $this->safeForeignKey('pending_sales', function (Blueprint $table) {
            $table->foreign('store_hub_id')->references('id')->on('store_hubs')->cascadeOnDelete();
        }, 'pending_sales_store_hub_id_foreign');

        $this->safeForeignKey('pending_sales', function (Blueprint $table) {
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
        }, 'pending_sales_submitted_by_foreign');

        $this->safeForeignKey('pending_sales', function (Blueprint $table) {
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
        }, 'pending_sales_confirmed_by_foreign');

        $this->safeForeignKey('users', function (Blueprint $table) {
            $table->foreign('hub_id')->references('id')->on('store_hubs')->nullOnDelete();
        }, 'users_hub_id_foreign');
    }

    public function down(): void
    {
        $this->dropForeignIfExists('sales_transactions', 'sales_transactions_store_hub_id_foreign');
        $this->dropForeignIfExists('transaction_items', 'transaction_items_product_id_foreign');
        $this->dropForeignIfExists('pending_sales', 'pending_sales_store_hub_id_foreign');
        $this->dropForeignIfExists('pending_sales', 'pending_sales_submitted_by_foreign');
        $this->dropForeignIfExists('pending_sales', 'pending_sales_confirmed_by_foreign');
        $this->dropForeignIfExists('users', 'users_hub_id_foreign');
    }

    private function safeForeignKey(string $table, Closure $callback, string $constraintName): void
    {
        if ($this->foreignKeyExists($table, $constraintName)) {
            return;
        }

        try {
            Schema::table($table, $callback);
        } catch (Throwable $e) {
            // Don't let one bad/legacy dataset block the whole migration run.
            // Check `php artisan db:show --table=' . $table . '` or the
            // Laravel log for the underlying error and clean up orphaned
            // rows manually, then re-run this migration.
            report($e);
        }
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (($foreignKey['name'] ?? null) === $constraintName) {
                return true;
            }
        }

        return false;
    }

    private function dropForeignIfExists(string $table, string $constraintName): void
    {
        if ($this->foreignKeyExists($table, $constraintName)) {
            Schema::table($table, function (Blueprint $table) use ($constraintName) {
                $table->dropForeign($constraintName);
            });
        }
    }
};
