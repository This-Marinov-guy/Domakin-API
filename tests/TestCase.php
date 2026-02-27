<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ensure each test method starts with a clean database connection.
     *
     * This helps avoid leaking "current transaction is aborted" (SQLSTATE 25P02)
     * across tests when a transaction has failed in a previous test.
     */
    protected function setUp(): void
    {
        // Drop any existing connection (and open transactions) from previous tests.
        try {
            DB::disconnect();
        } catch (\Throwable) {
            // Ignore disconnect failures in test bootstrap.
        }

        parent::setUp();

        // Establish a fresh connection for this test.
        try {
            DB::reconnect();
        } catch (\Throwable) {
            // Ignore reconnect failures; individual tests can still handle them.
        }
    }

    /**
     * Assert HTTP status and include the response body in the failure message.
     */
    protected function assertHttpStatus(TestResponse $response, int $expected): TestResponse
    {
        $this->assertSame(
            $expected,
            $response->status(),
            sprintf('[HTTP %d] %s', $response->status(), $response->getContent())
        );

        return $response;
    }

    /**
     * Assert HTTP status for a JsonResponse returned by a controller method
     * and include the response body in the failure message.
     *
     * @return array<string,mixed> Decoded JSON payload
     */
    protected function assertJsonStatus(JsonResponse $response, int $expected): array
    {
        $this->assertSame(
            $expected,
            $response->getStatusCode(),
            sprintf('[HTTP %d] %s', $response->getStatusCode(), $response->getContent())
        );

        return $response->getData(true);
    }
}
