<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class DemoConfigMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        if ($host === 'demo.domakin.nl') {
            // Override Axiom dataset for demo environment
            Config::set('services.axiom.dataset', env('DEMO_AXIOM_DATASET', config('services.axiom.dataset')));
            // Disable outbound email notifications on demo
            Config::set('mail.notifications_enabled', false);

            // Point default pgsql connection to DEMO_DB_* credentials
            Config::set('database.connections.pgsql.host', env('DEMO_DB_HOST', config('database.connections.pgsql.host')));
            Config::set('database.connections.pgsql.port', env('DEMO_DB_PORT', config('database.connections.pgsql.port')));
            Config::set('database.connections.pgsql.database', env('DEMO_DB_DATABASE', config('database.connections.pgsql.database')));
            Config::set('database.connections.pgsql.username', env('DEMO_DB_USERNAME', config('database.connections.pgsql.username')));
            Config::set('database.connections.pgsql.password', env('DEMO_DB_PASSWORD', config('database.connections.pgsql.password')));

            // Override Supabase to use DEMO_* variables
            Config::set('supabase.url', env('DEMO_SUPABASE_URL', config('supabase.url')));
            Config::set('supabase.service_role_key', env('DEMO_SUPABASE_SERVICE_ROLE_KEY', config('supabase.service_role_key')));
            Config::set('supabase.jwt_secret', env('DEMO_SUPABASE_JWT_SECRET', config('supabase.jwt_secret')));

            // Ensure connection uses updated configuration
            DB::purge('pgsql');
            DB::reconnect('pgsql');
        }

        return $next($request);
    }
}


