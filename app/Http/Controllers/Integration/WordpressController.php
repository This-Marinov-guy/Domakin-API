<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WordPressController extends Controller
{
    private const ENDPOINT = 'public-api.wordpress.com/wp/v2/sites/';
    private const PROTOCOL = 'https://'; // Assuming PROTOCOL is https

    /**
     * Get all WordPress posts
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPosts()
    {
        try {
            $response = Http::get(self::PROTOCOL . self::ENDPOINT . env('WORDPRESS_BLOG_ID') . '/posts');

            if (!$response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to load posts'
                ], 500);
            }

            $posts = collect($response->json())->map(function ($post, $index) {
                // Replace http with https
                $processedContent = str_replace(
                    'http://',
                    'https://',
                    $post['content']['rendered']
                );

                // Fix relative image paths
                $processedContent = str_replace(
                    'src="/wp-content',
                    'src=' . self::ENDPOINT . env('WORDPRESS_BLOG_ID') . '/wp-content',
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
                    'thumbnail' => $firstImageSrc,
                    'title' => str_replace('&nbsp;', ' ', $post['title']['rendered']),
                    'description' => $description,
                ];
            });

            return response()->json([
                'status' => true,
                'posts' => $posts
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific WordPress post details
     *
     * @param int $postId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPostDetails($postId)
    {
        try {
            $postResponse = Http::get(
                self::PROTOCOL . self::ENDPOINT . env('WORDPRESS_BLOG_ID') . "/posts/{$postId}",
                ['_embed' => true]
            );

            if (!$postResponse->successful()) {
                return response()->json([
                    'status' => false
                ], 200);
            }

            $stylesResponse = Http::get(
                self::PROTOCOL . env('WORDPRESS_BLOG_ID') . '/wp-includes/css/dist/block-library/style.min.css'
            );

            $post = $postResponse->json();

            // Process content
            $processedContent = str_replace(
                'http://',
                'https://',
                $post['content']['rendered']
            );

            $processedContent = str_replace(
                'src="/wp-content',
                'src=' . self::ENDPOINT . env('WORDPRESS_BLOG_ID') . '/wp-content',
                $processedContent
            );

            return response()->json([
                'status' => true,
                'data' => [
                    'title' => str_replace('&nbsp;', ' ', $post['title']['rendered']) ?? null,
                    'content' => $processedContent ?? null,
                    'styles' => $stylesResponse->successful() ? $stylesResponse->body() : null,
                ]
            ]);
        } catch (Exception $e) {
            Log::error('WordPress API Error: ' . $e->getMessage());
            return response()->json([
                'status' => false
            ], 200);
        }
    }
}
