<?php

namespace App\Support;

use Aws\Exception\AwsException;
use Throwable;

final class S3ErrorDiagnostics
{
    public static function from(Throwable $exception): array
    {
        while ($exception && ! $exception instanceof AwsException) {
            $exception = $exception->getPrevious();
        }

        if (! $exception instanceof AwsException) {
            return [];
        }

        $response = $exception->getResponse();
        if (! $response) {
            return [
                'aws_error_code' => $exception->getAwsErrorCode(),
                'aws_error_message' => $exception->getAwsErrorMessage(),
                'aws_request_id' => $exception->getAwsRequestId(),
            ];
        }

        $body = $response->getBody();
        $position = $body->isSeekable() ? $body->tell() : null;
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $responseBody = substr($body->getContents(), 0, 2000);
        if ($position !== null) {
            $body->seek($position);
        }

        return [
            'http_status' => $response->getStatusCode(),
            'content_type' => $response->getHeaderLine('Content-Type'),
            'request_id' => $response->getHeaderLine('x-request-id'),
            'aws_request_id' => $response->getHeaderLine('x-amz-request-id') ?: $exception->getAwsRequestId(),
            'response_body' => preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $responseBody),
        ];
    }
}
