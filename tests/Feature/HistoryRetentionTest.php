<?php

namespace Tests\Feature;

use App\Models\ProductFileRequest;
use App\Models\StaffActivityLog;
use App\Models\StoreHub;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class HistoryRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_path_indexes_are_installed(): void
    {
        foreach ([
            ['transaction_items', 'transaction_items_transaction_id_idx'],
            ['sales_payment_records', 'sales_payment_records_transaction_id_idx'],
            ['product_replacements', 'product_replacements_transaction_id_idx'],
            ['sales_transactions', 'sales_transactions_hub_order_date_id_idx'],
            ['sales_transactions', 'sales_transactions_hub_arrangement_date_idx'],
            ['pending_sales', 'pending_sales_hub_status_created_idx'],
            ['staff_activity_logs', 'staff_activity_logs_user_created_idx'],
            ['inventory_transactions', 'inventory_transactions_hub_occurred_id_idx'],
            ['fully_booked_orders', 'fully_booked_orders_hub_created_idx'],
            ['fully_booked_order_items', 'fully_booked_items_order_id_idx'],
        ] as [$table, $index]) {
            $this->assertTrue(Schema::hasIndex($table, $index), "{$table}.{$index} is missing.");
        }
    }

    public function test_pruning_removes_only_read_notifications_and_finalized_file_requests_past_retention(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $hub = StoreHub::create(['name' => 'Retention Hub', 'code' => 'RETENTION', 'status' => 'active']);
        $old = now()->subDays(100);
        $recent = now()->subDays(20);

        $oldRead = $this->notification($user, $old, $old);
        $oldUnread = $this->notification($user, null, $old);
        $recentRead = $this->notification($user, $recent, $recent);

        $completed = $this->fileRequest($hub, $user, 'approved', $old);
        $rejected = $this->fileRequest($hub, $user, 'rejected', $old);
        $pending = $this->fileRequest($hub, $user, 'pending', $old);
        $recentCompleted = $this->fileRequest($hub, $user, 'approved', $recent);
        DB::table('product_file_csv_chunks')->insert([
            'product_file_request_id' => $completed->id,
            'position' => 0,
            'content' => 'historical csv',
        ]);
        $activityLog = StaffActivityLog::create([
            'user_id' => $user->id,
            'store_hub_id' => $hub->id,
            'action_type' => 'product_import',
            'description' => 'Older audit log is retained.',
            'created_at' => $old,
            'updated_at' => $old,
        ]);

        $this->artisan('history:prune', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run only')
            ->assertExitCode(0);
        $this->assertDatabaseHas('notifications', ['id' => $oldRead]);
        $this->assertDatabaseHas('product_file_requests', ['id' => $completed->id]);
        $this->assertDatabaseHas('product_file_csv_chunks', ['product_file_request_id' => $completed->id]);

        $this->artisan('history:prune')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', ['id' => $oldRead]);
        $this->assertDatabaseHas('notifications', ['id' => $oldUnread]);
        $this->assertDatabaseHas('notifications', ['id' => $recentRead]);
        $this->assertDatabaseMissing('product_file_requests', ['id' => $completed->id]);
        $this->assertDatabaseMissing('product_file_requests', ['id' => $rejected->id]);
        $this->assertDatabaseMissing('product_file_csv_chunks', ['product_file_request_id' => $completed->id]);
        $this->assertDatabaseHas('product_file_requests', ['id' => $pending->id]);
        $this->assertDatabaseHas('product_file_requests', ['id' => $recentCompleted->id]);
        $this->assertDatabaseHas('staff_activity_logs', ['id' => $activityLog->id]);
    }

    public function test_pruning_rejects_nonpositive_retention_periods(): void
    {
        $this->artisan('history:prune', ['--notifications-days' => 0])
            ->expectsOutput('Retention periods must be positive whole numbers of days.')
            ->assertExitCode(2);
    }

    private function notification(User $user, mixed $readAt, mixed $createdAt): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'App\\Notifications\\SalesWorkflowNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => '{}',
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return $id;
    }

    private function fileRequest(StoreHub $hub, User $user, string $status, mixed $updatedAt): ProductFileRequest
    {
        return ProductFileRequest::create([
            'store_hub_id' => $hub->id,
            'submitted_by' => $user->id,
            'type' => 'import',
            'status' => $status,
            'processing_status' => 'completed',
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }
}
