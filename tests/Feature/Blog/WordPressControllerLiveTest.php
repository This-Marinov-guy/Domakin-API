<?php

namespace Tests\Feature\Blog;

use App\Http\Controllers\Integration\WordPressController;
use Tests\TestCase;

class WordPressControllerLiveTest extends TestCase
{
    private function skipIfLiveTestsDisabled(): void
    {
        if (! env('WORDPRESS_LIVE_TESTS')) {
            $this->markTestSkipped('Live WordPress API tests are disabled (WORDPRESS_LIVE_TESTS is not truthy).');
        }
    }

    public function test_live_get_posts_hits_real_wordpress_api_and_returns_success(): void
    {
        $this->skipIfLiveTestsDisabled();

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);

        $this->assertTrue($payload['status']);
        $this->assertIsArray($payload['data']);
    }

    public function test_live_get_post_details_for_first_post(): void
    {
        $this->skipIfLiveTestsDisabled();

        $controller = app(WordPressController::class);

        // First, fetch posts to discover a valid post ID.
        $postsResponse = $controller->getPosts();
        $postsPayload  = $this->assertJsonStatus($postsResponse, 200);

        $this->assertTrue($postsPayload['status']);
        $this->assertNotEmpty($postsPayload['data']);

        $firstPostId = $postsPayload['data'][0]['id'] ?? null;
        $this->assertNotNull($firstPostId, 'Expected at least one post with an id from the live WordPress API.');

        // Now retrieve the details for that post.
        $detailsResponse = $controller->getPostDetails($firstPostId);
        $detailsPayload  = $this->assertJsonStatus($detailsResponse, 200);

        $this->assertTrue($detailsPayload['status']);
        $this->assertSame($firstPostId, $detailsPayload['data']['id']);
        $this->assertArrayHasKey('title', $detailsPayload['data']);
        $this->assertArrayHasKey('content', $detailsPayload['data']);
    }
}

