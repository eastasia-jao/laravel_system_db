<?php

namespace App\Jobs;

use App\Http\Controllers\ProductFileRequestController;
use App\Models\ProductFileRequest;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $claimed = ProductFileRequest::whereKey($this->requestId)->where('status', 'pending')
            ->where('processing_status', 'queued')->update(['processing_status' => 'processing']);
        if (! $claimed) {
            return;
        }
        $previous = Auth::user();
        try {
            $reviewer = User::findOrFail($this->reviewerId);
            Auth::setUser($reviewer);
            $request = Request::create('/', 'POST', ['decision' => 'approved'], [], [], ['REMOTE_ADDR' => $this->ip]);
            app(ProductFileRequestController::class)->processReview($request, ProductFileRequest::findOrFail($this->requestId));
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
        // The data transaction has rolled back; staff can review and retry safely.
        ProductFileRequest::whereKey($this->requestId)->where('status', 'pending')->update([
            'processing_status' => 'failed',
            'processing_error' => 'Processing failed. No import changes were applied. Review the file and retry; contact the administrator if it fails again.',
        ]);
    }
}
