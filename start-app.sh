#!/bin/bash
set -e

echo "🚀 Starting Laravel Application..."

# Retry all failed jobs on startup
echo "🔄 Retrying failed jobs..."
FAILED_COUNT=$(php artisan queue:failed --json 2>/dev/null | grep -o '"uuid"' | wc -l || echo "0")

if [ "$FAILED_COUNT" -gt 0 ]; then
    echo "   Found $FAILED_COUNT failed job(s), retrying..."
    php artisan queue:retry all
    echo "✅ Failed jobs have been requeued"
else
    echo "✅ No failed jobs to retry"
fi

# Start Laravel web server
echo "▶️  Starting Laravel web server..."
exec php artisan serve --host=0.0.0.0 --port=8000
