<?php

namespace App\Http\Controllers;

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
    //
}
