<?php

namespace App\Support;

use Aws\S3\Exception\S3Exception;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;

final class AttachmentResponse
{
    public static function inline(
        FilesystemAdapter $disk,
        string $path,
        ?string $filename = null,
        array $headers = []
    ): StreamedResponse {
        return self::stream($disk, $path, HeaderUtils::DISPOSITION_INLINE, $filename, $headers);
    }

    public static function download(
        FilesystemAdapter $disk,
        string $path,
        ?string $filename = null,
        array $headers = []
    ): StreamedResponse {
        return self::stream($disk, $path, HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $headers);
    }

    private static function stream(
        FilesystemAdapter $disk,
        string $path,
        string $disposition,
        ?string $filename,
        array $headers
    ): StreamedResponse {
        try {
            $stream = $disk->readStream($path);
        } catch (FilesystemException|S3Exception $exception) {
            report($exception);

            abort(503, 'Attachment storage is temporarily unavailable.');
        }

        if (! is_resource($stream)) {
            abort(503, 'Attachment storage did not return a readable file stream.');
        }

        $filename = $filename ?: basename($path);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $contentType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

        return new StreamedResponse(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            200,
            array_merge([
                'Content-Type' => $contentType,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    $disposition,
                    $filename,
                    $filename
                ),
            ], $headers)
        );
    }
}
