<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class InventoryWorkflowNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $event,
        private readonly string $message,
        private readonly ?int $hubId = null,
        private readonly ?string $url = null,
        private readonly ?string $title = null,
        private readonly ?string $channel = null,
        private readonly ?string $reference = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $titles = [
            'product_file_request' => 'Product import/export approval needed',
            'product_file_reviewed' => 'Product import/export request reviewed',
            'import' => 'Product import completed',
            'export' => 'Product export completed',
            'inventory_verification' => 'Inventory verification completed',
            'stock_allocation' => 'Stock allocation updated',
            'transaction_log' => 'Inventory transaction recorded',
            'replacement_request' => 'Wholesale replacement verification needed',
            'replacement_approved' => 'Wholesale replacement approved',
            'replacement_rejected' => 'Wholesale replacement rejected',
            'return_recorded' => 'Return items recorded',
            'branch_transfer_request' => 'Branch transfer approval needed',
            'branch_transfer_approved' => 'Branch transfer approved',
            'branch_transfer_rejected' => 'Branch transfer rejected',
            'fully_booked_order' => 'Fully Booked order attachment to review',
            'fully_booked_completed' => 'Fully Booked order completed',
        ];

        return new DatabaseMessage([
            'event' => $this->event,
            'title' => $this->title ?? $titles[$this->event] ?? 'Inventory workflow updated',
            'message' => $this->message.' ('.$this->createdAtLabel().')',
            'url' => $this->url ?: ($this->hubId ? route('hub.dashboard', $this->hubId) : route('dashboard')),
            'hub_id' => $this->hubId,
            'channel' => $this->channel,
            'reference' => $this->reference,
        ]);
    }

    private function createdAtLabel(): string
    {
        return now()->format('M d, Y h:i A');
    }
}
