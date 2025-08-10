<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Response;

class AuthorizationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $role
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $role = '')
    {
        // Check if request has authorization token
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json([
                'message' => 'Authorization token not found'
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            // Decode the JWT token
            // You'll need to get your Supabase JWT secret from your Supabase dashboard
            $decoded = JWT::decode($token, new Key(config('supabase.jwt_secret'), 'HS256'));

            // The user ID is typically in the 'sub' claim
            $userId = $decoded->sub;

            // Role information might be in different places depending on your Supabase setup
            // It could be in app_metadata, user_metadata, or a custom claim
            // Adjust this according to your JWT structure
            $userRole = $decoded->user_roles ?? null;

            // Add user ID to request for use in controllers
            $request->attributes->add(['user_id' => $userId]);

            // Check if the user has the required role
            if (!empty($role) && $userRole !== $role) {
                return response()->json([
                    'message' => 'Access denied. You do not have the required role.'
                ], Response::HTTP_FORBIDDEN);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid or expired token: ' . $e->getMessage()
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
