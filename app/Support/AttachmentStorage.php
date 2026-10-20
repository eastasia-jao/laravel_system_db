<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

final class AttachmentStorage
{
    public const SALES = 'sales';

    public const EVENTS = 'events';

    public const INVENTORY = 'inventory';

    public static function diskName(string $category): string
    {
        return (string) config(
            "filesystems.attachment_disks.{$category}",
            config('filesystems.attachments', 'public')
        );
    }

    public static function disk(string $category): FilesystemAdapter
    {
        return Storage::disk(self::diskName($category));
    }
}
