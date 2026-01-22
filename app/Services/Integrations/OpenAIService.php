<?php

namespace App\Services\Integrations;

use App\Enums\OpenAIModels;
use App\Services\ExternalRequestLogger;
use App\Services\Helpers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class OpenAIService
{
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    private ExternalRequestLogger $externalRequestLogger;
    private string $apiKey;
    private OpenAIModels $model;

    public function __construct(ExternalRequestLogger $externalRequestLogger, OpenAIModels $model = OpenAIModels::GPT_4O_MINI)
    {
        $this->externalRequestLogger = $externalRequestLogger;
        $this->apiKey = env('OPENAI_API_KEY');
        $this->model = $model;

        if (empty($this->apiKey)) {
            Log::warning('OpenAI API key not configured');
        }
    }

    /**
     * Reformats and translates property description using OpenAI
     *
     * @param string $description The original description in English
     * @param array $languages Array of language codes (e.g., ['en', 'nl', 'de', 'fr'])
     * @return array Returns array with 'description' and 'title' keys, each containing translations
     * @throws Exception
     */
    public function reformatAndTranslateDescription(string $description, array $languages): array
    {
        if (env('APP_ENV') === 'dev') {
            Log::info('[OpenAI Service] Starting reformatAndTranslateDescription', [
                'description_length' => strlen($description),
                'description_preview' => substr($description, 0, 100) . '...',
                'languages' => $languages,
                'model' => $this->model->value,
            ]);
        }

        if (empty($this->apiKey)) {
            throw new Exception('OpenAI API key is not configured');
        }

        if (empty($description)) {
            throw new Exception('Description cannot be empty');
        }

        if (empty($languages)) {
            throw new Exception('At least one language must be provided');
        }

        // Ensure 'en' is in the languages array
        if (!in_array('en', $languages)) {
            $languages[] = 'en';
            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Added "en" to languages array', ['languages' => $languages]);
            }
        }

        // Create the prompt
        $prompt = $this->buildPrompt($description, $languages);

        if (env('APP_ENV') === 'dev') {
            Log::info('[OpenAI Service] Prompt created', [
                'prompt_length' => strlen($prompt),
                'prompt_preview' => substr($prompt, 0, 200) . '...',
            ]);
        }

        try {
            $requestHeaders = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ];

            $requestBody = [
                'model' => $this->model->value,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional real estate content writer specializing in creating structured, professional, and neutral property descriptions for rooms and apartments. You provide accurate translations and SEO-optimized titles.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.7,
            ];

            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Making API request', [
                    'endpoint' => self::API_ENDPOINT,
                    'model' => $this->model->value,
                    'has_api_key' => !empty($this->apiKey),
                    'request_body_size' => strlen(json_encode($requestBody)),
                ]);
            }

            $response = Http::withHeaders($requestHeaders)
                ->timeout(60)
                ->post(self::API_ENDPOINT, $requestBody);

            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] API response received', [
                    'status' => $response->status(),
                    'response_size' => strlen($response->body()),
                ]);
            }

            // Log external request
            $this->externalRequestLogger->log(
                'POST',
                self::API_ENDPOINT,
                $requestHeaders,
                $requestBody,
                $response,
                'OpenAIService',
                [
                    'description_length' => strlen($description),
                    'languages' => $languages,
                ]
            );

            if (!$response->successful()) {
                $errorMessage = $response->json()['error']['message'] ?? 'OpenAI API request failed';
                Log::error('OpenAI API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'error' => $errorMessage,
                ]);
                throw new Exception('OpenAI API request failed: ' . $errorMessage);
            }

            $responseData = $response->json();
            $content = $responseData['choices'][0]['message']['content'] ?? null;

            if (empty($content)) {
                if (env('APP_ENV') === 'dev') {
                    Log::warning('[OpenAI Service] Empty content in response', [
                        'response_data' => $responseData,
                    ]);
                }
                throw new Exception('OpenAI API returned empty response');
            }

            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Raw content received', [
                    'content_length' => strlen($content),
                    'content_preview' => substr($content, 0, 300) . '...',
                ]);
            }

            // Parse JSON response
            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if (env('APP_ENV') === 'dev') {
                    Log::error('[OpenAI Service] Failed to parse JSON', [
                        'content' => $content,
                        'json_error' => json_last_error_msg(),
                    ]);
                }
                throw new Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
            }

            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] JSON parsed successfully', [
                    'has_description' => isset($result['description']),
                    'has_title' => isset($result['title']),
                    'has_slug' => isset($result['slug']),
                    'description_keys' => isset($result['description']) ? array_keys($result['description']) : [],
                    'title_keys' => isset($result['title']) ? array_keys($result['title']) : [],
                ]);
            }

            // Validate and structure the response
            $structured = $this->validateAndStructureResponse($result, $languages);

            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Response structured and validated', [
                    'final_languages' => array_keys($structured['description']),
                    'slug' => $structured['slug'],
                    'slug_length' => strlen($structured['slug']),
                ]);
            }

            return $structured;

        } catch (Exception $e) {
            Log::error('Error in OpenAI service: ' . $e->getMessage(), [
                'description_length' => strlen($description),
                'languages' => $languages,
            ]);
            throw $e;
        }
    }

    /**
     * Build the prompt for OpenAI
     *
     * @param string $description
     * @param array $languages
     * @return string
     */
    private function buildPrompt(string $description, array $languages): string
    {
        $languagesList = implode(', ', $languages);
        $languageCodes = implode('", "', $languages);

        return <<<PROMPT
You are a professional real estate content writer. Please reformat and translate the following property description.

Original description (in English):
{$description}

Requirements:
1. Reformulate the description to be:
   - Structured and well-organized
   - Professional and polished
   - Written from a neutral perspective
   - Suitable for real estate listings (rooms/apartments)
   - Clear and informative
   - Engaging but not overly promotional

2. Create translations for the following languages: {$languagesList}

3. Generate a brief, SEO-optimized title (maximum 6 words) that describes the property. The title should be:
   - SEO-friendly for English
   - Concise and descriptive
   - Professional
   - Translated to all requested languages

4. Generate a URL-friendly slug (English only) based on the English title. The slug should be:
   - Under 70 characters
   - Lowercase
   - Spaces replaced with hyphens (-)
   - Only alphanumeric characters and hyphens
   - SEO-friendly and descriptive
   - Do not include the property ID in the slug
   - Do not include the city in the slug
   - Do not include sensitive information in the slug
   - Do not include any special characters in the slug
   - Example: "cozy-room-wawaw" or "modern-apartment-city-center"

Please return a JSON object with the following structure:
{
  "description": {
    "{$languageCodes}": "translated description for each language"
  },
  "title": {
    "{$languageCodes}": "translated title for each language (max 5 words each)"
  },
  "slug": "url-friendly-slug-in-english-under-90-chars"
}

Ensure all translations are accurate, culturally appropriate, and maintain the professional tone. The English version should be the reformatted, improved version of the original description.
PROMPT;
    }

    /**
     * Validate and structure the OpenAI response
     *
     * @param array $result
     * @param array $languages
     * @return array
     * @throws Exception
     */
    private function validateAndStructureResponse(array $result, array $languages): array
    {
        // Validate structure
        if (!isset($result['description']) || !isset($result['title'])) {
            throw new Exception('Invalid response structure: missing description or title');
        }

        if (!is_array($result['description']) || !is_array($result['title'])) {
            throw new Exception('Invalid response structure: description and title must be objects');
        }

        // Generate slug if not provided or invalid
        $slug = $this->generateSlug($result);

        // Ensure all languages are present
        $missingLanguages = [];
        foreach ($languages as $lang) {
            if (!isset($result['description'][$lang])) {
                $missingLanguages[] = "description.{$lang}";
            }
            if (!isset($result['title'][$lang])) {
                $missingLanguages[] = "title.{$lang}";
            }
        }

        if (!empty($missingLanguages)) {
            Log::warning('Missing translations in OpenAI response', [
                'missing' => $missingLanguages,
                'received' => array_keys($result['description']),
            ]);
            if (env('APP_ENV') === 'dev') {
                Log::warning('[OpenAI Service] Missing translations detected', [
                    'missing' => $missingLanguages,
                    'expected_languages' => $languages,
                    'received_languages' => array_keys($result['description']),
                ]);
            }
        }

        // Structure the response
        $structured = [
            'description' => [],
            'title' => [],
            'slug' => $slug,
        ];

        // Fill in descriptions
        foreach ($languages as $lang) {
            $structured['description'][$lang] = $result['description'][$lang] ?? '';
        }

        // Fill in titles
        foreach ($languages as $lang) {
            $structured['title'][$lang] = $result['title'][$lang] ?? '';
        }

        return $structured;
    }

    /**
     * Generate or validate slug from OpenAI response
     *
     * @param array $result
     * @return string
     */
    private function generateSlug(array $result): string
    {
        // If slug is provided and valid, use it
        if (isset($result['slug']) && is_string($result['slug'])) {
            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Slug provided in response', [
                    'original_slug' => $result['slug'],
                    'original_length' => strlen($result['slug']),
                ]);
            }
            $slug = $result['slug'];
            if (strlen($slug) <= 90 && !empty($slug)) {
                if (env('APP_ENV') === 'dev') {
                    Log::info('[OpenAI Service] Using provided slug', ['slug' => $slug]);
                }
                return $slug;
            }
            if (env('APP_ENV') === 'dev') {
                Log::warning('[OpenAI Service] Provided slug invalid, generating from title', [
                    'sanitized_slug' => $slug,
                    'length' => strlen($slug),
                ]);
            }
        }

        // Generate slug from English title as fallback
        $englishTitle = $result['title']['en'] ?? '';
        if (empty($englishTitle)) {
            // If no English title, use a default
            $englishTitle = 'available-room';
            if (env('APP_ENV') === 'dev') {
                Log::warning('[OpenAI Service] No English title found, using default', ['default' => $englishTitle]);
            }
        } else {
            if (env('APP_ENV') === 'dev') {
                Log::info('[OpenAI Service] Generating slug from English title', [
                    'title' => $englishTitle,
                ]);
            }
        }

        $slug = Helpers::sanitizeSlug($englishTitle);
        if (env('APP_ENV') === 'dev') {
            Log::info('[OpenAI Service] Generated slug', [
                'slug' => $slug,
                'length' => strlen($slug),
            ]);
        }

        return $slug;
    }
}
