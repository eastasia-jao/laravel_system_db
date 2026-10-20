<?php

namespace App\Support;

use Aws\S3\Exception\S3Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;

final class AttachmentAvailability
{
    public static function check(FilesystemAdapter $disk, string $path): ?bool
    {
        try {
            return $disk->exists($path) && $disk->size($path) > 0;
        } catch (FilesystemException|S3Exception $exception) {
            report($exception);

            return null;
        }
    }
}
