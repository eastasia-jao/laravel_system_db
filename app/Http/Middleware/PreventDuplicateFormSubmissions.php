<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateFormSubmissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $submissionId = $request->input('_submission_id');

        if ($submissionId === null || $submissionId === '') {
            return $next($request);
        }

        if (! is_string($submissionId) || ! Str::isUuid($submissionId)) {
            return $request->expectsJson()
                ? response()->json(['message' => 'The form submission identifier is invalid. Please reload and try again.'], 422)
                : back()->with('error', 'This form could not be verified. Please reload the page and try again.');
        }

        $key = 'form-submission:'.hash('sha256', $submissionId);

        if (! Cache::add($key, true, now()->addDay())) {
            return $request->expectsJson()
                ? response()->json(['message' => 'This form has already been submitted. Please wait for the first submission to finish.'], 409)
                : back()->with('error', 'This form has already been submitted. Please wait for the first submission to finish.');
        }

        $response = $next($request);

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            Cache::forget($key);
        }

        return $response;
    }
}
