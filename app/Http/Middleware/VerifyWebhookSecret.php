<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header('X-Webhook-Secret') !== config('supabase.webhook_secret')) {
            Log::warning('Unauthorized webhook request', ['request' => $request->all()]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
