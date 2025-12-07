<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProdFirewallMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedDomains = config('domains.allowed_domains', []);
        
        // Add dev domains if in development environment
        if (env('APP_ENV') === 'dev') {
            $allowedDomains = array_merge($allowedDomains, config('domains.dev_domains', []));
        }

        // Allow public endpoints (like Swagger docs) without origin check
        if ($this->isPublicEndpoint($request)) {
            return $next($request);
        }

        // Get origin - headers are case-insensitive in Laravel
        $origin = $request->header('Origin') ?? $request->header('Referer');
        $originHost = $this->extractHost($origin);

        // Block if origin/referrer is missing or not in allowed list
        if (!$originHost || !$this->isAllowed($originHost, $allowedDomains)) {
            return response()->json([
                'message' => 'Forbidden by firewall',
            ], 403);
        }

        return $next($request);
    }

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

    private function isAllowed(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }
        return false;
    }
    private function isPublicEndpoint(Request $request): bool
    {
        $path = $request->path();

        if ($request->method() === 'POST' && $this->matchesPattern($path, 'api/webhook/stripe/*')) {
            // Allow POST only for webhooks
            return true;
        }

        // Check if the path matches any of our public patterns from config
        $publicPatterns = config('firewall.public_patterns', []);
        foreach ($publicPatterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a path matches a wildcard pattern
     * 
     * @param string $path The path to check
     * @param string $pattern The pattern to match against (can contain * wildcards)
     * @return bool
     */
    private function matchesPattern(string $path, string $pattern): bool
    {
        // If the pattern doesn't contain wildcards, use simple string comparison
        if (strpos($pattern, '*') === false) {
            return $path === $pattern;
        }

        // Convert the pattern to a regular expression
        $regex = preg_quote($pattern, '/');
        $regex = str_replace('\*', '.*', $regex);

        return (bool) preg_match('/^' . $regex . '$/', $path);
    }
}
