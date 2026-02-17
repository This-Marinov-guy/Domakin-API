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
     * Reformats and translates property description, flatmates, and period context using OpenAI
     *
     * @param string $description The original description in English
     * @param array $languages Array of language codes (e.g., ['en', 'nl', 'de', 'fr'])
     * @param string|null $flatmates The flatmates value (usually a number)
     * @param string|null $period The period value
     * @return array Returns array with 'description', 'title', 'flatmates', 'period', 'slug'
     * @throws Exception
     */
    public function reformatAndTranslateDescription(
        string $description,
        array $languages,
        ?string $flatmates = null,
        ?string $period = null
    ): array
    {
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
        }

        // Create the prompt
        $prompt = $this->buildPrompt($description, $languages, $flatmates, $period);

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
                $testResult = [
                    'description' => [
                        'en' => 'This is a test description',
                    ],
                    'title' => [
                        'en' => 'This is a test title',
                    ],
                    'slug' => 'this-is-a-test-slug',
                ];
                
                // Add test translations for additional fields
                foreach ($languages as $lang) {
                    $testResult['flatmates'][$lang] = $flatmates ?? '2';
                    $testResult['period'][$lang] = $period ?? '12 months';
                }

                return $testResult;
            }

            $response = Http::withHeaders($requestHeaders)
                ->timeout(60)
                ->post(self::API_ENDPOINT, $requestBody);

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
                throw new Exception('OpenAI API returned empty response');
            }



            // Parse JSON response
            $result = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Failed to parse OpenAI response: ' . json_last_error_msg());
            }

            // Validate and structure the response
            $structured = $this->validateAndStructureResponse($result, $languages);

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
     * @param string|null $flatmates
     * @param string|null $period
     * @return string
     */
    private function buildPrompt(string $description, array $languages, ?string $flatmates = null, ?string $period = null): string
    {
        $languagesList = implode(', ', $languages);
        $languageCodes = implode('", "', $languages);

        // Build additional fields section
        $additionalFields = '';
        if ($flatmates !== null || $period !== null) {
            $additionalFields = "\n\nAdditional property information (extract also from description if provided):\n";
            if ($flatmates !== null) {
                $additionalFields .= "- Flatmates: {$flatmates} (usually a number, e.g., '2', '3-4', and genders if provided - the first number is for male and the second is for females. Format: 'none', '2' or '2 male' or '2 female' or '2 (male and female)' or '2 male and 1 female' or '2 female and 1 male' etc.). If it will be only you, dont mention the gender, type none.\n";
            }
            if ($period !== null) {
                $additionalFields .= "- Period: {$period} (rental period, e.g., '12 months from now', '6-12 months from now', 'indefinite' and dates if provided. Format: '12 months from now' or '12 months from [date]' or 'indefinite' or '12 months to [date]'). Always mention start date or available from now\n";
            }
        }

        return <<<PROMPT
You are a professional real estate content writer. Please reformat and translate the following property description and additional information.

Original description (in English):
{$description}{$additionalFields}

Requirements:
1. Reformulate the description to be:
   - Structured and well-organized
   - Professional and polished
   - Written from a neutral perspective
   - Suitable for real estate listings (rooms/apartments)
   - Clear and informative
   - Engaging but not overly promotional
   - no emojis or special characters, just brief paragraphs and sentences.

   Turn the description into html format with paragraphs by meaning - use p tags and br tags for new lines.

2. Create translations for the following languages: {$languagesList}

3. Generate a brief, SEO-optimized title (maximum 6 words) that describes the property. The title should be:
   - SEO-friendly for English
   - Concise and descriptive
   - Professional
   - Translated to all requested languages

4. Translate the additional fields (flatmates, period) to all requested languages:
   - Flatmates: Keep numbers as-is, but translate any descriptive text (e.g., "mixed" → appropriate translation)
   - Period: Translate time periods appropriately (e.g., "12 months" → "12 maanden" in Dutch, "12 μήνες" in Greek)

5. Generate a URL-friendly slug (English only) based on the English title. The slug should be:
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
    "{$languageCodes}": "translated title for each language (max 6 words each)"
  },
  "flatmates": {
    "{$languageCodes}": "translated flatmates value for each language"
  },
  "period": {
    "{$languageCodes}": "translated period value for each language"
  },
  "slug": "url-friendly-slug-in-english-under-70-chars"
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
            // Check optional fields
            if (isset($result['flatmates']) && !isset($result['flatmates'][$lang])) {
                $missingLanguages[] = "flatmates.{$lang}";
            }
            if (isset($result['period']) && !isset($result['period'][$lang])) {
                $missingLanguages[] = "period.{$lang}";
            }
        }

        if (!empty($missingLanguages)) {
            Log::warning('Missing translations in OpenAI response', [
                'missing' => $missingLanguages,
                'received' => array_keys($result['description']),
            ]);
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

        // Fill in flatmates if provided
        if (isset($result['flatmates']) && is_array($result['flatmates'])) {
            $structured['flatmates'] = [];
            foreach ($languages as $lang) {
                $structured['flatmates'][$lang] = $result['flatmates'][$lang] ?? '';
            }
        }

        // Fill in period if provided
        if (isset($result['period']) && is_array($result['period'])) {
            $structured['period'] = [];
            foreach ($languages as $lang) {
                $structured['period'][$lang] = $result['period'][$lang] ?? '';
            }
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
            $slug = $result['slug'];
        }

        // Generate slug from English title as fallback
        $englishTitle = $result['title']['en'] ?? '';
        if (empty($englishTitle)) {
            // If no English title, use a default
            $englishTitle = 'available-room';
        }

        $slug = Helpers::sanitizeSlug($englishTitle);
    
        return $slug;
    }
}
