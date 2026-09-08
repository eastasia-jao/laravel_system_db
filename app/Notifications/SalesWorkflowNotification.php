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
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $channel = strtoupper((string) $this->sale->sales_channel);
        $order = $this->sale->invoice_number ?: 'Pending sale #'.$this->sale->id;
        $eventTime = ($this->event === 'confirmed' ? $this->sale->updated_at : $this->sale->created_at)
            ?->format('M d, Y h:i A') ?? now()->format('M d, Y h:i A');
        $messages = [
            'submitted' => "New {$channel} sale {$order} is ready for inventory verification. Submitted {$eventTime}.",
            'confirmed' => "{$channel} sale {$order} was verified and inventory was deducted at {$eventTime}.",
            'rejected' => "{$channel} sale {$order} was rejected at {$eventTime}. Reason: ".($this->sale->rejection_reason ?: 'No reason provided.'),
        ];

        return new DatabaseMessage([
            'event' => $this->event,
            'title' => match ($this->event) {
                'submitted' => 'New sale for verification',
                'confirmed' => 'Sale verification completed',
                'rejected' => 'Sale rejected by inventory',
                default => 'Sales workflow updated',
            },
            'message' => $messages[$this->event] ?? 'Sales workflow updated.',
            'url' => $this->event === 'rejected'
                ? route('sales.rejected')
                : route('hub.sales.pending', $this->sale->store_hub_id),
            'sale_id' => $this->sale->id,
            'hub_id' => $this->sale->store_hub_id,
        ]);
    }
}
