#!/bin/bash
set -e

echo "🚀 Starting Laravel Application with Queue Worker..."

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
until php artisan db:show &>/dev/null; do
    echo "   Database not ready, waiting 2 seconds..."
    sleep 2
done
echo "✅ Database connection established"

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

# Start queue worker in background
echo "▶️  Starting queue worker in background..."
php artisan queue:work database \
    --sleep=5 \
    --tries=3 \
    --max-time=3600 \
    --timeout=60 \
    --max-jobs=1000 \
    --memory=512 &

QUEUE_PID=$!
echo "✅ Queue worker started (PID: $QUEUE_PID)"

# Function to cleanup on exit
cleanup() {
    echo "🛑 Stopping services..."
    kill $QUEUE_PID 2>/dev/null || true
    wait $QUEUE_PID 2>/dev/null || true
    echo "✅ Queue worker stopped"
    exit 0
}

trap cleanup SIGTERM SIGINT

# Start Laravel web server in foreground
echo "▶️  Starting Laravel web server..."
exec php artisan serve --host=0.0.0.0 --port=8000
