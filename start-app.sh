#!/bin/bash
set -e

echo "🚀 Starting Laravel Application..."

# Retry all failed jobs on startup
echo "🔄 Checking for failed jobs..."

# Check for failed jobs using artisan
FAILED_OUTPUT=$(php artisan queue:failed 2>&1)

# Check if there are no failed jobs
if echo "$FAILED_OUTPUT" | grep -qi "No failed jobs"; then
    echo "✅ No failed jobs to retry"
else
    # Extract UUIDs from the output (Laravel shows them in the table)
    UUIDs=$(echo "$FAILED_OUTPUT" | grep -oE "[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}" | sort -u)
    
    if [ -z "$UUIDs" ]; then
        # Fallback: use 'retry all' if we can't extract UUIDs
        echo "   Found failed jobs, retrying all..."
        php artisan queue:retry all 2>&1
        echo "✅ Failed jobs have been requeued"
    else
        # Count UUIDs
        FAILED_COUNT=$(echo "$UUIDs" | wc -l | tr -d ' ')
        echo "   Found $FAILED_COUNT failed job(s), retrying..."
        
        # Retry each job
        while IFS= read -r uuid; do
            if [ -n "$uuid" ]; then
                php artisan queue:retry "$uuid" >/dev/null 2>&1 || true
            fi
        done <<< "$UUIDs"
        
        echo "✅ Failed jobs have been requeued"
    fi
fi

# Start Laravel web server
echo "▶️  Starting Laravel web server..."
exec php artisan serve --host=0.0.0.0 --port=8000
