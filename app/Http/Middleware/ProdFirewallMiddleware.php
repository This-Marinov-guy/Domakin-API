<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProdFirewallMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO: Remove this once we have a proper firewall that wont block SSR requests
        if (true) {            
            $allowedDomains = [
                'domakin.nl',
                'demo.domakin.nl',
            ];

            $originHost = $this->extractHost($request->headers->get('Origin'))
                ?? $this->extractHost($request->headers->get('Referer'))
                ?? null;

            // Block if origin/referrer is missing or not in allowed list
            if ((!$originHost || !$this->isAllowed($originHost, $allowedDomains)) && !$this->isPublicEndpoint($request)) {
                return response()->json([
                    'message' => 'Forbidden by firewall',
                ], 403);
            }
        }

        return $next($request);
    }

    private function extractHost(?string $url): ?string
    {
        if (!$url) {
            return null;
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
    private static array $publicPatterns = [
        'api/webhooks/stripe/*',
        'api/blog/*',
        'api/property/listing',
        'api/property/list',
        'api/feedback/list',
    ];

    private function isPublicEndpoint(Request $request): bool
    {
        // Only allow GET for public endpoints (except webhooks which need POST)
        if ($request->method() !== 'GET') {
            $path = $request->path();
            // Allow POST only for webhooks
            return $request->method() === 'POST' && $this->matchesPattern($path, 'api/webhook/stripe/*');
        }

        // Check if the path matches any of our public patterns
        $path = $request->path();
        foreach (self::$publicPatterns as $pattern) {
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


