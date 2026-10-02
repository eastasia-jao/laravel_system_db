<?php

namespace App\Jobs;

use App\Http\Controllers\ProductFileRequestController;
use App\Models\ProductFileRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProductFileRequest implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    // Large spreadsheet imports can exceed the default 10-minute job limit.
    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public function __construct(public int $requestId, public int $reviewerId, public string $ip)
    {
        $this->onConnection(config('inventory.queue_connection', 'inventory'));
        $this->onQueue('product-files');
    }

    public function handle(): void
    {
        Log::info('Product file job started.', ['request_id' => $this->requestId]);
        $claimed = ProductFileRequest::whereKey($this->requestId)->where('status', 'pending')
            ->where('processing_status', 'queued')->update(['processing_status' => 'processing']);
        if (! $claimed) {
            Log::warning('Product file job skipped because it was not queued.', ['request_id' => $this->requestId]);

            return;
        }
        $previous = Auth::user();
        try {
            $reviewer = User::findOrFail($this->reviewerId);
            Auth::setUser($reviewer);
            $request = Request::create('/', 'POST', ['decision' => 'approved'], [], [], ['REMOTE_ADDR' => $this->ip]);
            app(ProductFileRequestController::class)->processReview($request, ProductFileRequest::findOrFail($this->requestId));
            Log::info('Product file job completed.', ['request_id' => $this->requestId]);
        } finally {
            if ($previous) {
                Auth::setUser($previous);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Product file job failed.', [
            'request_id' => $this->requestId,
            'error' => $exception?->getMessage(),
        ]);

        // The data transaction has rolled back; staff can review and retry safely.
        ProductFileRequest::whereKey($this->requestId)->where('status', 'pending')->update([
            'processing_status' => 'failed',
            'processing_error' => 'Processing failed. No import changes were applied. Review the file and retry; contact the administrator if it fails again.',
        ]);
    }
}
