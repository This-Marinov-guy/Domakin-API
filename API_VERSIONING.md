# API Versioning Guide

This document explains how API versioning is implemented and how to add new versions.

## Current Version

**API Version 1 (v1)** is currently active. All endpoints are prefixed with `/api/v1/`.

## Route Structure

All API routes are organized under version prefixes in `routes/api.php`:

```php
Route::prefix('v1')->group(function () {
    // All v1 routes here
});
```

## Adding a New Version (e.g., v2)

To add a new API version:

1. **Add a new route group** in `routes/api.php`:
   ```php
   Route::prefix('v2')->group(function () {
       // Add v2 specific routes here
       // You can reuse controllers or create new versioned controllers
   });
   ```

2. **Create versioned controllers** (optional):
   - Option A: Reuse existing controllers (same logic, different routes)
   - Option B: Create new controllers in `app/Http/Controllers/V2/` for breaking changes

3. **Update Swagger annotations**:
   - Update `@OA\Info` version in `app/Http/Controllers/Controller.php`
   - Update all `path` annotations to use `/api/v2/` instead of `/api/v1/`

4. **Update firewall config** (if needed):
   - The firewall config uses wildcards (`api/v*/`) so it should work automatically
   - If you need version-specific rules, update `config/firewall.php`

## Current Endpoints (v1)

All endpoints are now accessible at:
- `/api/v1/common/newsletter/*`
- `/api/v1/blog/*`
- `/api/v1/feedback/*`
- `/api/v1/viewing/*`
- `/api/v1/renting/*`
- `/api/v1/career/*`
- `/api/v1/property/*`
- `/api/v1/authentication/*`
- `/api/v1/user/*`

## Legacy Endpoints

- `/api/webhooks/stripe/checkout` - Webhook endpoint (no versioning for compatibility)

## Firewall Configuration

The firewall config (`config/firewall.php`) uses wildcard patterns to support all versions:
- `api/v*/blog/*` - Matches v1, v2, v3, etc.
- `api/v*/property/*` - Matches all versions
- `api/v*/feedback/list` - Matches all versions

## Swagger Documentation

Swagger documentation is automatically generated and includes all versioned endpoints. Access it at:
- `http://localhost:8000/api/documentation`

## Best Practices

1. **Backward Compatibility**: When adding v2, consider keeping v1 active for a transition period
2. **Breaking Changes**: Use new versions for breaking changes, not minor updates
3. **Documentation**: Update this file when adding new versions
4. **Testing**: Test all endpoints after version changes

## Migration Strategy

When deprecating an old version:
1. Announce deprecation with a timeline
2. Keep the old version active during the transition
3. Add deprecation headers to old version responses
4. Remove the old version after the transition period

