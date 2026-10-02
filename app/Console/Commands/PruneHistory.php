<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneHistory extends Command
{
    protected $signature = 'history:prune
        {--notifications-days=90 : Remove read notifications older than this many days}
        {--file-requests-days=90 : Remove completed file requests older than this many days}
        {--dry-run : Report eligible records without deleting them}';

    protected $description = 'Prune expired read notifications and completed product file requests';

    public function handle(): int
    {
        $notificationDays = filter_var($this->option('notifications-days'), FILTER_VALIDATE_INT);
        $fileRequestDays = filter_var($this->option('file-requests-days'), FILTER_VALIDATE_INT);
        if ($notificationDays === false || $notificationDays < 1 || $fileRequestDays === false || $fileRequestDays < 1) {
            $this->error('Retention periods must be positive whole numbers of days.');

            return self::INVALID;
        }

        $notificationCutoff = now()->subDays($notificationDays);
        $fileRequestCutoff = now()->subDays($fileRequestDays);
        $notifications = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', $notificationCutoff);
        $fileRequests = DB::table('product_file_requests')
            ->whereIn('status', ['approved', 'rejected'])
            ->where('updated_at', '<', $fileRequestCutoff);

        $notificationCount = (clone $notifications)->count();
        $fileRequestCount = (clone $fileRequests)->count();
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $notificationsAffected = $notificationCount;
            $fileRequestsAffected = $fileRequestCount;
        } else {
            [$notificationsAffected, $fileRequestsAffected] = DB::transaction(fn () => [
                $notifications->delete(),
                $fileRequests->delete(),
            ]);
        }

        $this->table(['Record type', 'Eligible', $isDryRun ? 'Would remove' : 'Removed'], [
            ['Read notifications', $notificationCount, $notificationsAffected],
            ['Completed product file requests', $fileRequestCount, $fileRequestsAffected],
        ]);

        if ($isDryRun) {
            $this->comment('Dry run only; no records were deleted.');
        }

        return self::SUCCESS;
    }
}
