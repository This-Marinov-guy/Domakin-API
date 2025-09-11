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
        if (app()->environment('prod') && false) {
            // Allow webhook postbacks without an Origin/Referer
            if ($request->is('api/webhook/stripe')) {
                return $next($request);
            }
            $allowedDomains = [
                'domakin.nl',
                'demo.domakin.nl',
            ];

            $originHost = $this->extractHost($request->headers->get('Origin'))
                ?? $this->extractHost($request->headers->get('Referer'))
                ?? null;

            // Block if origin/referrer is missing or not in allowed list
            if (!$originHost || !$this->isAllowed($originHost, $allowedDomains)) {
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
}


