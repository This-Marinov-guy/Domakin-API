<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
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
