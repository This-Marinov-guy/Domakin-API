<?php

namespace App\Models\Concerns;

use Illuminate\Http\Request;

trait HasDomainBasedTermsValidation
{
    /**
     * Get terms validation rules based on domain
     *
     * @param \Illuminate\Http\Request|null $request
     * @return array
     */
    protected static function getTermsValidationRules($request = null): array
    {
        if (static::requiresTerms($request)) {
            return [
                'terms' => 'required|array',
                'terms.contact' => 'required|accepted',
                'terms.legals' => 'required|accepted',
            ];
        }

        return [
            'terms' => 'nullable|array',
            'terms.contact' => 'nullable|accepted',
            'terms.legals' => 'nullable|accepted',
        ];
    }

    /**
     * Check if terms are required based on the request origin domain
     *
     * @param \Illuminate\Http\Request|null $request
     * @return bool
     */
    public static function requiresTerms($request = null): bool
    {
        if (!$request) {
            // If no request provided, default to requiring terms (backward compatibility)
            return true;
        }

        $origin = $request->header('Origin') ?? $request->header('Referer');
        $originHost = static::extractHost($origin);

        if (!$originHost) {
            // If no origin, default to requiring terms
            return true;
        }

        $termsRequiredDomains = config('domains.terms_required_domains', []);

        // Check if the origin domain requires terms
        foreach ($termsRequiredDomains as $domain) {
            // Exact match
            if ($originHost === $domain) {
                return true;
            }
            
            // Subdomain match (e.g., www.domakin.nl matches domakin.nl)
            if (str_ends_with($originHost, '.' . $domain)) {
                return true;
            }
        }

        // Domain not in the list, terms are optional
        return false;
    }

    /**
     * Extract host from URL
     *
     * @param string|null $url
     * @return string|null
     */
    protected static function extractHost(?string $url): ?string
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
}

