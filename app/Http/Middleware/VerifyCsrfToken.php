<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     * This is populated dynamically from config.
     *
     * @var array<int, string>
     */
    protected $except = [];

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function inExceptArray($request): bool
    {
        $path = $request->path();
        $excludedPatterns = config('firewall.csrf_excluded', []);

        foreach ($excludedPatterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return parent::inExceptArray($request);
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
