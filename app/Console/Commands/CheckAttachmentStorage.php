<?php

namespace App\Console\Commands;

use App\Support\AttachmentStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class CheckAttachmentStorage extends Command
{
    protected $signature = 'attachments:health';

    protected $description = 'Verify configured attachment disks can write, read, and delete a temporary probe';

    public function handle(): int
    {
        $failed = false;

        foreach ([AttachmentStorage::SALES, AttachmentStorage::EVENTS, AttachmentStorage::INVENTORY] as $category) {
            $diskName = AttachmentStorage::diskName($category);
            $path = 'health-checks/'.$category.'-'.Str::uuid().'.png';
            $contents = base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
                true
            );
            $disk = null;

            try {
                $disk = Storage::disk($diskName);
                $written = $disk->put($path, $contents, ['ContentType' => 'image/png']);
                $readBack = $written ? $disk->get($path) : null;

                if (! $written || $readBack !== $contents) {
                    throw new \RuntimeException('The probe could not be read back correctly.');
                }

                $this->info("{$category}: OK ({$diskName})");
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$category}: FAILED ({$diskName}) - {$exception->getMessage()}");
                $failed = true;
            } finally {
                if ($disk) {
                    try {
                        $disk->delete($path);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->warn("{$category}: temporary probe cleanup failed ({$diskName})");
                        $failed = true;
                    }
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
