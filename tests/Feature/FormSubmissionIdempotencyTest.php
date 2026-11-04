<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventDuplicateFormSubmissions;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormSubmissionIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_replayed_submission_identifier_is_rejected_without_running_the_action_twice(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $middleware = new PreventDuplicateFormSubmissions();
        $submissionId = (string) Str::uuid();
        $actionCount = 0;
        $next = function () use (&$actionCount) {
            $actionCount++;

            return response()->json(['saved' => true]);
        };

        $firstResponse = $middleware->handle(
            Request::create('/test-submit', 'POST', ['_submission_id' => $submissionId], server: ['HTTP_ACCEPT' => 'application/json']),
            $next
        );
        $duplicateResponse = $middleware->handle(
            Request::create('/test-submit', 'POST', ['_submission_id' => $submissionId], server: ['HTTP_ACCEPT' => 'application/json']),
            $next
        );

        $this->assertSame(200, $firstResponse->getStatusCode());
        $this->assertSame(409, $duplicateResponse->getStatusCode());
        $this->assertSame(1, $actionCount);
    }

    public function test_distinct_submission_identifiers_are_allowed(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $middleware = new PreventDuplicateFormSubmissions();
        $actionCount = 0;
        $next = function () use (&$actionCount) {
            $actionCount++;

            return response()->json(['saved' => true]);
        };

        foreach ([Str::uuid(), Str::uuid()] as $submissionId) {
            $response = $middleware->handle(
                Request::create('/test-submit', 'POST', ['_submission_id' => (string) $submissionId], server: ['HTTP_ACCEPT' => 'application/json']),
                $next
            );
            $this->assertSame(200, $response->getStatusCode());
        }

        $this->assertSame(2, $actionCount);
    }

    public function test_web_routes_reject_a_replayed_submission_identifier(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        Route::middleware('web')->post('/test-form-submission', fn () => response()->json(['saved' => true]));

        $submissionId = (string) Str::uuid();
        $this->postJson('/test-form-submission', ['_submission_id' => $submissionId])->assertOk();
        $this->postJson('/test-form-submission', ['_submission_id' => $submissionId])->assertConflict();
    }

    public function test_validation_responses_release_the_submission_identifier_for_a_corrected_retry(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();

        $middleware = new PreventDuplicateFormSubmissions();
        $submissionId = (string) Str::uuid();
        $next = fn () => response()->json(['errors' => ['field' => ['Invalid value.']]], 422);

        $firstResponse = $middleware->handle(
            Request::create('/test-submit', 'POST', ['_submission_id' => $submissionId], server: ['HTTP_ACCEPT' => 'application/json']),
            $next
        );
        $retryResponse = $middleware->handle(
            Request::create('/test-submit', 'POST', ['_submission_id' => $submissionId], server: ['HTTP_ACCEPT' => 'application/json']),
            fn () => response()->json(['saved' => true])
        );

        $this->assertSame(422, $firstResponse->getStatusCode());
        $this->assertSame(200, $retryResponse->getStatusCode());
    }

    public function test_prune_command_removes_only_expired_form_submission_records(): void
    {
        config(['cache.default' => 'database']);

        $expiredKey = 'form-submission:expired-test';
        $activeKey = 'form-submission:active-test';
        $unrelatedKey = 'unrelated-cache-key';
        DB::table('cache')->insert([
            ['key' => $expiredKey, 'value' => '1', 'expiration' => now()->subMinute()->timestamp],
            ['key' => $activeKey, 'value' => '1', 'expiration' => now()->addHour()->timestamp],
            ['key' => $unrelatedKey, 'value' => '1', 'expiration' => now()->subMinute()->timestamp],
        ]);

        $this->artisan('form-submissions:prune')->assertSuccessful();

        $this->assertDatabaseMissing('cache', ['key' => $expiredKey]);
        $this->assertDatabaseHas('cache', ['key' => $activeKey]);
        $this->assertDatabaseHas('cache', ['key' => $unrelatedKey]);
    }
}
