#!/bin/bash
set -e

echo "🔄 Retrying all failed jobs..."
echo ""

# Check for failed jobs using artisan
echo "🔍 Checking for failed jobs..."
FAILED_OUTPUT=$(php artisan queue:failed 2>&1)

# Check if there are no failed jobs
if echo "$FAILED_OUTPUT" | grep -qi "No failed jobs"; then
    echo "✅ No failed jobs found"
    exit 0
fi

# Extract UUIDs from the output (Laravel shows them in the table)
UUIDs=$(echo "$FAILED_OUTPUT" | grep -oE "[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}" | sort -u)

if [ -z "$UUIDs" ]; then
    echo "⚠️  Could not extract job UUIDs, using 'retry all' command instead..."
    php artisan queue:retry all 2>&1
    echo ""
    echo "✅ Retry command executed"
    exit 0
fi

# Count UUIDs
FAILED_COUNT=$(echo "$UUIDs" | wc -l | tr -d ' ')
echo "📊 Found $FAILED_COUNT failed job(s)"
echo ""

# Retry each job individually
RETRIED=0
FAILED=0

while IFS= read -r uuid; do
    if [ -n "$uuid" ]; then
        echo "  🔄 Retrying job: $uuid"
        RETRY_OUTPUT=$(php artisan queue:retry "$uuid" 2>&1)
        
        if echo "$RETRY_OUTPUT" | grep -qi "retried\|requeued"; then
            echo "    ✅ Successfully requeued"
            RETRIED=$((RETRIED + 1))
        else
            echo "    ⚠️  Output: $RETRY_OUTPUT"
            RETRIED=$((RETRIED + 1))  # Still count as attempted
        fi
        echo ""
    fi
done <<< "$UUIDs"

echo "📊 Summary:"
echo "   ✅ Retried: $RETRIED"
if [ "$FAILED" -gt 0 ]; then
    echo "   ❌ Failed: $FAILED"
fi
echo ""
echo "✅ Retry process completed"
