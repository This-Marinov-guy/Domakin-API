<?php

namespace Tests\Feature\Blog;

use App\Http\Controllers\Integration\WordPressController;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WordPressControllerDirectTest extends TestCase
{
    private function fakePost(array $overrides = []): array
    {
        return array_merge([
            'id'      => 1,
            'slug'    => 'test-post',
            'title'   => ['rendered' => 'Test &amp; Post Title'],
            'content' => ['rendered' => '<p>Short description here.</p>'],
        ], $overrides);
    }

    private function fakeStyles(): string
    {
        return '.wp-block-image { display: block; }';
    }

    // ---------------------------------------------------------------
    // getPosts — GET /api/v1/blog/posts
    // ---------------------------------------------------------------

    public function test_get_posts_returns_200_with_mapped_posts(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([
                $this->fakePost(['id' => 1, 'slug' => 'post-one']),
                $this->fakePost(['id' => 2, 'slug' => 'post-two']),
            ], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertCount(2, $payload['data']);
        $this->assertSame(1, $payload['data'][0]['id']);
        $this->assertSame('post-one', $payload['data'][0]['slug']);
        $this->assertSame(2, $payload['data'][1]['id']);
        $this->assertSame('post-two', $payload['data'][1]['slug']);
    }

    public function test_get_posts_decodes_html_entities_in_title(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([
                $this->fakePost(['title' => ['rendered' => 'Hello &amp; World']]),
            ], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertSame('Hello & World', $payload['data'][0]['title']);
    }

    public function test_get_posts_replaces_http_with_https_in_content(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([
                $this->fakePost(['content' => ['rendered' => '<img src="http://example.com/image.jpg">']]),
            ], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertSame('https://example.com/image.jpg', $payload['data'][0]['thumbnail']);
    }

    public function test_get_posts_extracts_first_image_as_thumbnail(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([
                $this->fakePost([
                    'content' => ['rendered' =>
                        '<img src="https://first.com/img.jpg"><img src="https://second.com/img.jpg">'
                    ],
                ]),
            ], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertSame('https://first.com/img.jpg', $payload['data'][0]['thumbnail']);
    }

    public function test_get_posts_truncates_description_for_non_first_posts(): void
    {
        $longText = str_repeat('A', 100); // > 80 chars

        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([
                $this->fakePost(['id' => 1, 'content' => ['rendered' => "<p>{$longText}</p>"]]),
                $this->fakePost(['id' => 2, 'content' => ['rendered' => "<p>{$longText}</p>"]]),
            ], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 200);
        // First post keeps full description
        $this->assertSame($longText, $payload['data'][0]['description']);
        // Non-first posts are truncated to 80 chars + ellipsis
        $this->assertSame(str_repeat('A', 80) . '...', $payload['data'][1]['description']);
    }

    public function test_get_posts_returns_500_when_api_fails(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([], 500),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 500);
        $this->assertFalse($payload['status']);
    }

    public function test_get_posts_returns_400_on_connection_exception(): void
    {
        Http::fake(fn ($request) => throw new ConnectionException('Network error'));

        $controller = app(WordPressController::class);
        $response   = $controller->getPosts();

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // getPostDetails — GET /api/v1/blog/post/{id}
    // ---------------------------------------------------------------

    public function test_get_post_details_returns_200_with_post_data(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts/1*' => Http::response(
                $this->fakePost(['id' => 1, 'slug' => 'test-post']),
                200
            ),
            '*style.min.css' => Http::response($this->fakeStyles(), 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostDetails(1);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(1, $payload['data']['id']);
        $this->assertSame('test-post', $payload['data']['slug']);
        $this->assertArrayHasKey('title', $payload['data']);
        $this->assertArrayHasKey('content', $payload['data']);
        $this->assertArrayHasKey('styles', $payload['data']);
    }

    public function test_get_post_details_includes_styles_when_css_endpoint_succeeds(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts/1*' => Http::response(
                $this->fakePost(['id' => 1]),
                200
            ),
            '*style.min.css' => Http::response($this->fakeStyles(), 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostDetails(1);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertSame($this->fakeStyles(), $payload['data']['styles']);
    }

    public function test_get_post_details_styles_is_null_when_css_endpoint_fails(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts/1*' => Http::response(
                $this->fakePost(['id' => 1]),
                200
            ),
            '*style.min.css' => Http::response('', 500),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostDetails(1);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertNull($payload['data']['styles']);
    }

    public function test_get_post_details_returns_status_false_when_post_not_found(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts/999*' => Http::response(
                ['code' => 'rest_post_invalid_id'],
                404
            ),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostDetails(999);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertFalse($payload['status']);
    }

    public function test_get_post_details_returns_400_on_connection_exception(): void
    {
        Http::fake(fn ($request) => throw new ConnectionException('Network error'));

        $controller = app(WordPressController::class);
        $response   = $controller->getPostDetails(1);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    // ---------------------------------------------------------------
    // getPostBySlug — GET /api/v1/blog/post-by-slug/{slug}
    // ---------------------------------------------------------------

    public function test_get_post_by_slug_returns_200_with_post_data(): void
    {
        Http::fake([
            // First call: slug search returns matching post in array
            '*slug=my-slug*' => Http::response(
                [$this->fakePost(['id' => 5, 'slug' => 'my-slug'])],
                200
            ),
            // Second call: fetch full post by resolved ID
            '*posts/5*' => Http::response(
                $this->fakePost(['id' => 5, 'slug' => 'my-slug']),
                200
            ),
            '*style.min.css' => Http::response($this->fakeStyles(), 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostBySlug('my-slug');

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertSame(5, $payload['data']['id']);
        $this->assertSame('my-slug', $payload['data']['slug']);
        $this->assertArrayHasKey('content', $payload['data']);
    }

    public function test_get_post_by_slug_returns_status_false_when_slug_not_found(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response([], 200),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostBySlug('nonexistent-slug');

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertFalse($payload['status']);
    }

    public function test_get_post_by_slug_returns_status_false_when_api_not_successful(): void
    {
        Http::fake([
            '*public-api.wordpress.com*posts*' => Http::response(null, 500),
        ]);

        $controller = app(WordPressController::class);
        $response   = $controller->getPostBySlug('my-slug');

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertFalse($payload['status']);
    }

    public function test_get_post_by_slug_returns_400_on_connection_exception(): void
    {
        Http::fake(fn ($request) => throw new ConnectionException('Network error'));

        $controller = app(WordPressController::class);
        $response   = $controller->getPostBySlug('my-slug');

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }
}
