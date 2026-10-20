<?php

namespace App\Support;

use Aws\CommandInterface;

final class SupabaseS3UploadOptions
{
    public static function removeUnsupportedAcl(CommandInterface $command): void
    {
        unset($command['ACL']);
    }
}
