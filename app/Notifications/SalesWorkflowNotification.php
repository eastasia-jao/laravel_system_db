<?php

namespace App\Notifications;

use App\Models\PendingSale;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class SalesWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $event,
        private readonly PendingSale $sale,
        private readonly ?string $actorName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $channel = strtoupper((string) $this->sale->sales_channel);
        $reportChannel = strtolower(str_replace(['-', ' '], '_', (string) $this->sale->sales_channel));
        $reportChannel = match ($reportChannel) {
            'shopee', 'lazada', 'tiktok', 'online', 'wholesale', 'walk_in', 'walkin' => $reportChannel === 'walkin' ? 'walk_in' : $reportChannel,
            default => 'all',
        };
        $order = $this->sale->invoice_number ?: 'Sale #'.$this->sale->id;
        $eventTime = ($this->event === 'confirmed'
            ? $this->sale->confirmed_at
            : ($this->event === 'rejected' ? $this->sale->rejected_at : $this->sale->created_at))
            ?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A');
        $actor = $this->actorName ? " by {$this->actorName}" : '';
        $submitter = $this->sale->submittedBy?->name ?? 'Unknown staff member';
        $messages = [
            'submitted' => "New {$channel} sale {$order} submitted by {$submitter} is ready for inventory verification. Submitted {$eventTime}.",
            'confirmed' => "{$channel} sale {$order} was approved{$actor} and stock was deducted at {$eventTime}.",
            'rejected' => "{$channel} sale {$order} was rejected{$actor} at {$eventTime}. Reason: ".($this->sale->rejection_reason ?: 'No reason provided.'),
        ];

        return new DatabaseMessage([
            'event' => $this->event,
            'title' => match ($this->event) {
                'submitted' => 'New sale for verification',
                'confirmed' => 'Sale approved by inventory',
                'rejected' => 'Sale rejected by inventory',
                default => 'Sales workflow updated',
            },
            'message' => $messages[$this->event] ?? 'Sales workflow updated.',
            'url' => $this->event === 'rejected'
                ? route('sales.rejected')
                : ($this->event === 'confirmed'
                    ? route('hub.report', [
                        'hub' => $this->sale->store_hub_id,
                        'channel' => $reportChannel,
                    ])
                    : route('hub.sales.pending', $this->sale->store_hub_id)),
            'sale_id' => $this->sale->id,
            'hub_id' => $this->sale->store_hub_id,
        ]);
    }
}
