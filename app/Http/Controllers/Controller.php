<?php

namespace App\Http\Controllers;

use App\Constants\Translations;
use Illuminate\Http\Request;

/**
 * @OA\Info(
 *     title="Domakin API",
 *     version="1.0.0",
 *     description="API documentation for Domakin platform - Version 1",
 *     @OA\Contact(
 *         email="support@domakin.nl"
 *     )
 * )
 * 
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter your JWT token"
 * )
 * 
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints"
 * )
 * @OA\Tag(
 *     name="Properties",
 *     description="Property management endpoints"
 * )
 * @OA\Tag(
 *     name="Renting",
 *     description="Renting application endpoints"
 * )
 * @OA\Tag(
 *     name="Viewing",
 *     description="Property viewing scheduling endpoints"
 * )
 * @OA\Tag(
 *     name="Feedback",
 *     description="Feedback management endpoints"
 * )
 * @OA\Tag(
 *     name="Newsletter",
 *     description="Newsletter subscription endpoints"
 * )
 * @OA\Tag(
 *     name="Blog",
 *     description="Blog posts endpoints"
 * )
 * @OA\Tag(
 *     name="Career",
 *     description="Career application endpoints"
 * )
 * @OA\Tag(
 *     name="User",
 *     description="User profile endpoints"
 * )
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Webhook endpoints"
 * )
 */
abstract class Controller
{
    protected function requestLocale(Request $request): string
    {
        $language = $request->header('Accept-Language')
            ?: $request->header('X-Language')
            ?: $request->header('X-Locale')
            ?: 'en';

        $language = strtolower(trim(explode(',', $language)[0]));
        $language = str_replace('_', '-', $language);

        if (str_starts_with($language, 'el')) {
            $language = 'gr';
        }

        $locale = explode('-', $language)[0] ?: 'en';

        return in_array($locale, Translations::WEB_SUPPORTED_LOCALES, true)
            ? $locale
            : 'en';
    }
}
