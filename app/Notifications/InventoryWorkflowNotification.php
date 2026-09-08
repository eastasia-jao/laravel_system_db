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
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): DatabaseMessage
    {
        $titles = [
            'import' => 'Product import completed',
            'export' => 'Product export completed',
            'inventory_verification' => 'Inventory verification completed',
            'stock_allocation' => 'Stock allocation updated',
            'transaction_log' => 'Inventory transaction recorded',
        ];

        return new DatabaseMessage([
            'event' => $this->event,
            'title' => $titles[$this->event] ?? 'Inventory workflow updated',
            'message' => $this->message.' ('.$this->createdAtLabel().')',
            'url' => $this->url ?: ($this->hubId ? route('hub.dashboard', $this->hubId) : route('dashboard')),
            'hub_id' => $this->hubId,
        ]);
    }

    private function createdAtLabel(): string
    {
        return now()->format('M d, Y h:i A');
    }
}
