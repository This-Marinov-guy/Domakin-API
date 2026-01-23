#!/bin/bash
set -e

echo "🚀 Starting Laravel Application..."

# Retry all failed jobs on startup
echo "🔄 Checking for failed jobs..."

# Query the database directly to count failed jobs
FAILED_COUNT=$(php -r "
require __DIR__ . '/vendor/autoload.php';
\$app = require_once __DIR__ . '/bootstrap/app.php';
\$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    \$count = Illuminate\Support\Facades\DB::table('failed_jobs')->count();
    echo \$count;
} catch (Exception \$e) {
    echo '0';
}
" 2>/dev/null || echo "0")

if [ "$FAILED_COUNT" -gt 0 ]; then
    echo "   Found $FAILED_COUNT failed job(s) in failed_jobs table, retrying..."
    php artisan queue:retry all
    echo "✅ Failed jobs have been requeued"
else
    echo "✅ No failed jobs to retry"
fi

# Start Laravel web server
echo "▶️  Starting Laravel web server..."
exec php artisan serve --host=0.0.0.0 --port=8000
