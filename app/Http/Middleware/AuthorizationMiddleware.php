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
            // Resolve the correct JWT secret (demo host may use DEMO_SUPABASE_JWT_SECRET)
            $jwtSecret = config('supabase.jwt_secret');
            // Resolve by Origin/Referer host (frontend) rather than API host
            $originHeader = $request->headers->get('Origin') ?: $request->headers->get('Referer');
            $originHost = $originHeader ? (parse_url($originHeader, PHP_URL_HOST) ?: null) : null;
            if ($originHost === 'demo.domakin.nl') {
                $jwtSecret = env('DEMO_SUPABASE_JWT_SECRET', $jwtSecret);
            }

            // Decode the JWT token
            $decoded = JWT::decode($token, new Key($jwtSecret, 'HS256'));

            // The user ID is typically in the 'sub' claim
            $userId = $decoded->sub;

            // Try resolving role from common Supabase claim locations
            $userRole = null;
            if (isset($decoded->user_roles)) {
                $userRole = $decoded->user_roles;
            } elseif (isset($decoded->app_metadata) && is_object($decoded->app_metadata)) {
                if (isset($decoded->app_metadata->role)) {
                    $userRole = $decoded->app_metadata->role;
                } elseif (isset($decoded->app_metadata->roles) && is_array($decoded->app_metadata->roles)) {
                    $userRole = $decoded->app_metadata->roles[0] ?? null;
                }
            } elseif (isset($decoded->role)) {
                $userRole = $decoded->role;
            }

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
