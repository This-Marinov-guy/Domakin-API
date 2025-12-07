<?php

namespace App\Http\Controllers\Integration;

use App\Classes\ApiResponseClass;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * @OA\Tag(name="Blog")
 */
class WordPressController extends Controller
{
    private const API_ENDPOINT = 'public-api.wordpress.com/wp/v2/sites/';
    private const PROTOCOL = 'https://';
    private const PER_PAGE = 100;
    private const PAGE = 1;

    /**
     * Get WordPress blog domain from environment
     */
    private function getBlogDomain()
    {
        return env('WORDPRESS_BLOG_DOMAIN', 'domakin0.wordpress.com');
    }

    /**
     * @OA\Get(
     *     path="/api/blog/posts",
     *     summary="Get all blog posts",
     *     tags={"Blog"},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to load posts"),
     *             @OA\Property(property="tag", type="string")
     *         )
     *     )
     * )
     * Get all WordPress posts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPosts()
    {
        try {
            $response = Http::get(self::PROTOCOL . self::API_ENDPOINT . env('WORDPRESS_BLOG_ID') . '/posts', [
                'per_page' => self::PER_PAGE,
                'page' => self::PAGE,
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to load posts'
                ], 500);
            }

            $posts = collect($response->json())->map(function ($post, $index) {
                $processedContent = $post['content']['rendered'];

                // Replace http with https
                $processedContent = str_replace('http://', 'https://', $processedContent);

                // Fix relative image paths to use the blog domain
                $processedContent = str_replace(
                    'src="/wp-content',
                    'src="https://' . $this->getBlogDomain() . '/wp-content',
                    $processedContent
                );

                // Extract first image source
                preg_match('/<img[^>]+src="([^">]+)"/', $processedContent, $matches);
                $firstImageSrc = $matches[1] ?? null;

                // Extract description
                preg_match('/<p[^>]*>([^<|&]+)<\/p>/', $processedContent, $match);
                $description = $match[1] ?? 'Curious to learn more about this article? Click below and jump right to it!';

                if ($index !== 0) {
                    $description = strlen(trim($description)) > 80
                        ? substr(trim($description), 0, 80) . '...'
                        : trim($description);
                }

                return [
                    'id' => $post['id'],
                    'slug' => $post['slug'],
                    'thumbnail' => $firstImageSrc,
                    'title' => trim(html_entity_decode(strip_tags($post['title']['rendered']), ENT_QUOTES, 'UTF-8')),
                    'description' => $description,
                ];
            });

            return ApiResponseClass::sendSuccess($posts);
        } catch (Exception $e) {
            Log::error('WordPress API Error: ' . $e);
            return ApiResponseClass::sendError("Failed to load posts");
        }
    }

    /**
     * @OA\Get(
     *     path="/api/blog/post/{id}",
     *     summary="Get blog post by ID",
     *     tags={"Blog"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Post ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     * Get specific WordPress post details by ID
     *
     * @param int $postId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPostDetails($postId)
    {
        try {
            $postResponse = Http::get(
                self::PROTOCOL . self::API_ENDPOINT . env('WORDPRESS_BLOG_ID') . "/posts/{$postId}",
                ['_embed' => true]
            );

            if (!$postResponse->successful()) {
                return response()->json([
                    'status' => false
                ], 200);
            }

            return $this->formatPostResponse($postResponse);
        } catch (Exception $e) {
            Log::error('WordPress API Error: ' . $e->getMessage());

            return ApiResponseClass::sendError($e->getMessage());
        }
    }
    
    /**
     * @OA\Get(
     *     path="/api/blog/post-by-slug/{slug}",
     *     summary="Get blog post by slug",
     *     tags={"Blog"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="Post slug",
     *         required=true,
     *         @OA\Schema(type="string", example="my-blog-post")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     * Get specific WordPress post details by slug
     *
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPostBySlug($slug)
    {
        try {
            $postResponse = Http::get(
                self::PROTOCOL . self::API_ENDPOINT . env('WORDPRESS_BLOG_ID') . "/posts",
                [
                    'slug' => $slug,
                    '_embed' => true
                ]
            );

            if (!$postResponse->successful() || empty($postResponse->json())) {
                return response()->json([
                    'status' => false,
                    'message' => 'Post not found'
                ], 200);
            }

            // WordPress returns an array of posts when using slug parameter
            // We need to get the first (and usually only) item
            $postResponse = Http::get(
                self::PROTOCOL . self::API_ENDPOINT . env('WORDPRESS_BLOG_ID') . "/posts/" . $postResponse->json()[0]['id'],
                ['_embed' => true]
            );

            return $this->formatPostResponse($postResponse);
        } catch (Exception $e) {
            Log::error('WordPress API Error: ' . $e->getMessage());

            return ApiResponseClass::sendError($e->getMessage());
        }
    }
    
    /**
     * Format post response with common processing
     * 
     * @param \Illuminate\Http\Client\Response $postResponse
     * @return \Illuminate\Http\JsonResponse
     */
    private function formatPostResponse($postResponse)
    {
        $stylesResponse = Http::get(
            'https://' . $this->getBlogDomain() . '/wp-includes/css/dist/block-library/style.min.css'
        );

        $post = $postResponse->json();
        $processedContent = $post['content']['rendered'];

        // Replace http with https
        $processedContent = str_replace('http://', 'https://', $processedContent);

        // Fix relative image paths to use the blog domain
        $processedContent = str_replace(
            'src="/wp-content',
            'src="https://' . $this->getBlogDomain() . '/wp-content',
            $processedContent
        );

        return ApiResponseClass::sendSuccess([
            'id' => $post['id'],
            'slug' => $post['slug'],
            'title' => trim(html_entity_decode(strip_tags($post['title']['rendered']), ENT_QUOTES, 'UTF-8')),
            'content' => $processedContent ?? null,
            'styles' => $stylesResponse->successful() ? $stylesResponse->body() : null,
        ]);
    }
}
