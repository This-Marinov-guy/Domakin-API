<?php

namespace App\Http\Middleware;

use App\Models\AppCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomainWhitelistMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get origin - headers are case-insensitive in Laravel
        $origin = $request->header('Origin') ?? $request->header('Referer');
        $originHost = $this->extractHost($origin);


        if (!$originHost) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        // First, check config-based domain whitelist
        $allowedDomains = config('domains.allowed_domains', []);

        // Add dev domains if in development/local environment
        if ($this->isDevelopmentEnvironment()) {
            $allowedDomains = array_merge($allowedDomains, config('domains.dev_domains', []));
        }

        if ($this->isAllowed($originHost, $allowedDomains)) {
            return $next($request);
        }

        // If not in config, check database for domain + auth token match
        $authToken = $this->extractAuthToken($request);

        if ($authToken) {
            try {
                $appCredential = AppCredential::findByAuthorization($authToken);

                if ($appCredential) {
                    return $next($request);
                }
            } catch (\Exception $e) {
                // If table doesn't exist or database error, log and continue
                \Illuminate\Support\Facades\Log::warning('AppCredential lookup failed: ' . $e->getMessage());
            }
        }

        // If neither check passes, block the request
        return response()->json([
            'message' => 'Access denied',
        ], 403);
    }

    /**
     * Extract host from URL
     *
     * @param string|null $url
     * @return string|null
     */
    private function extractHost(?string $url): ?string
    {
        if (!$url) {
            return null;
        }

        // If URL doesn't have a protocol, parse_url won't work correctly
        // Check if it's already just a hostname
        if (!preg_match('/^https?:\/\//', $url)) {
            // It's likely just a hostname, return as-is
            return trim($url);
        }

        $parts = parse_url($url);
        return $parts['host'] ?? null;
    }

    /**
     * Check if host is allowed
     *
     * @param string $host
     * @param array $allowedDomains
     * @return bool
     */
    private function isAllowed(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $allowed) {
            // Exact match
            if ($host === $allowed) {
                return true;
            }

            // Subdomain match (e.g., www.domakin.nl matches domakin.nl)
            if (str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract auth token from Authorization header
     *
     * @param Request $request
     * @return string|null
     */
    private function extractAuthToken(Request $request): ?string
    {
        $authorization = $request->header('Authorization');

        if (!$authorization) {
            return null;
        }

        // Check for Bearer token format
        if (preg_match('/Bearer\s+(.*)$/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        // If not Bearer format, return the whole header value
        return trim($authorization);
    }

    private function isDevelopmentEnvironment(): bool
    {
        $env = env('APP_ENV');
        return $env === 'dev' || $env === 'local';
    }
}
