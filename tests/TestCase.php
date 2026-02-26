<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
}
