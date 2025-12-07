# Swagger API Documentation

This document explains how to access, view, and regenerate the Swagger/OpenAPI documentation for the Domakin API.

## 📖 Accessing the Documentation

### Web Interface (Swagger UI)

Once the documentation is generated, you can access it via:

**Local Development:**
```
http://localhost:8000/api/documentation
```

**Production:**
```
https://your-domain.com/api/documentation
```

The Swagger UI provides an interactive interface where you can:
- Browse all API endpoints
- See request/response schemas
- Test endpoints directly from the browser
- View authentication requirements

### JSON Documentation

The raw OpenAPI JSON specification is available at:

```
http://localhost:8000/docs/api-docs.json
```

This can be used with:
- Postman (import OpenAPI spec)
- Other API clients
- Code generation tools

## 🔄 Regenerating Documentation

After making changes to Swagger annotations in your controllers, you need to regenerate the documentation.

### Command

```bash
php artisan l5-swagger:generate
```

This command will:
1. Scan all controllers in `app/Http/Controllers` for Swagger annotations
2. Generate the OpenAPI specification
3. Save it to `storage/api-docs/api-docs.json`
4. Update the Swagger UI interface

### When to Regenerate

You should regenerate the documentation when:
- Adding new endpoints
- Modifying existing endpoint annotations
- Changing request/response schemas
- Updating API descriptions
- Adding new tags or security schemes

### Automated Regeneration (Optional)

You can add this to your deployment script or CI/CD pipeline:

```bash
php artisan l5-swagger:generate
```

## 📋 Viewing Documentation

### Method 1: Swagger UI (Recommended)

1. Start your Laravel development server:
   ```bash
   php artisan serve
   ```

2. Open your browser and navigate to:
   ```
   http://localhost:8000/api/documentation
   ```

3. You'll see the interactive Swagger UI with all your endpoints organized by tags.

### Method 2: JSON File

View the raw JSON specification:

```bash
cat storage/api-docs/api-docs.json
```

Or use a JSON viewer/formatter:

```bash
cat storage/api-docs/api-docs.json | python3 -m json.tool
```

### Method 3: Import into API Clients

**Postman:**
1. Open Postman
2. Click "Import"
3. Select "Link" tab
4. Enter: `http://localhost:8000/docs/api-docs.json`
5. Click "Continue" and "Import"

**Insomnia:**
1. Open Insomnia
2. Go to Application → Preferences → Data
3. Click "Import Data" → "From URL"
4. Enter: `http://localhost:8000/docs/api-docs.json`

## 🔧 Configuration

### Environment Variables

Add to your `.env` file:

```env
# Swagger Configuration
L5_SWAGGER_CONST_HOST=http://localhost:8000
L5_SWAGGER_USE_ABSOLUTE_PATH=true
L5_FORMAT_TO_USE_FOR_DOCS=json
```

### Config File

Main configuration: `config/l5-swagger.php`

Key settings:
- **Documentation Route**: `api/documentation` (configurable)
- **JSON Output**: `storage/api-docs/api-docs.json`
- **Scan Path**: `app/` directory
- **Excluded Files**: Auth controllers (configured in `scanOptions.exclude`)

## 📝 Adding Documentation to New Endpoints

To document a new endpoint, add Swagger annotations above your controller method:

```php
/**
 * @OA\Post(
 *     path="/api/your-endpoint",
 *     summary="Your endpoint description",
 *     tags={"YourTag"},
 *     security={{"bearerAuth":{}}},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"field1", "field2"},
 *             @OA\Property(property="field1", type="string", example="value1"),
 *             @OA\Property(property="field2", type="integer", example=123)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Success",
 *         @OA\JsonContent(
 *             @OA\Property(property="success", type="boolean", example=true),
 *             @OA\Property(property="data", type="object")
 *         )
 *     )
 * )
 */
public function yourMethod(Request $request): JsonResponse
{
    // Your code here
}
```

## 🏷️ Available Tags

The API is organized into the following tags:

- **Authentication** - User registration and validation
- **Properties** - Property management
- **Renting** - Renting applications
- **Viewing** - Property viewing scheduling
- **Feedback** - Feedback management
- **Newsletter** - Newsletter subscriptions
- **Blog** - Blog posts
- **Career** - Career applications
- **User** - User profile management
- **Webhooks** - Webhook endpoints

## 🔐 Authentication

Most endpoints require Bearer token authentication. To test authenticated endpoints in Swagger UI:

1. Click the "Authorize" button at the top of the Swagger UI
2. Enter your JWT token in the format: `Bearer your-token-here`
3. Click "Authorize"
4. All subsequent requests will include the token

## 🚀 Quick Start

1. **Generate documentation:**
   ```bash
   php artisan l5-swagger:generate
   ```

2. **Start the server:**
   ```bash
   php artisan serve
   ```

3. **Open in browser:**
   ```
   http://localhost:8000/api/documentation
   ```

## 📚 Additional Resources

- [Swagger/OpenAPI Specification](https://swagger.io/specification/)
- [L5-Swagger Documentation](https://github.com/DarkaOnLine/L5-Swagger)
- [Swagger Annotations Guide](https://zircote.github.io/swagger-php/)

## ⚠️ Troubleshooting

### Can't access `/api/documentation`?

**Issue:** Getting 403 Forbidden or firewall blocking access.

**Solution:**
1. Swagger routes are automatically added to public patterns in `config/firewall.php`
2. Clear config cache:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```
3. Verify routes are registered:
   ```bash
   php artisan route:list | grep documentation
   ```
4. Check that the documentation file exists:
   ```bash
   ls -la storage/api-docs/api-docs.json
   ```

### Documentation not updating?

1. Clear cache:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```

2. Regenerate:
   ```bash
   php artisan l5-swagger:generate
   ```

### Getting warnings about missing controllers?

These are harmless warnings about controllers that may not exist (e.g., `EmailVerificationNotificationController`). They don't affect documentation generation. You can exclude them in `config/l5-swagger.php` under `scanOptions.exclude`.

### Server not running?

Start the Laravel development server:
```bash
php artisan serve
```

Then access: `http://localhost:8000/api/documentation`

### Still having issues?

1. Check if the firewall middleware is blocking:
   - Swagger routes should be in `config/firewall.php` under `public_patterns`
   - Routes: `api/documentation`, `api/oauth2-callback`, `docs`, `docs/*`

2. Verify L5-Swagger is installed:
   ```bash
   composer show darkaonline/l5-swagger
   ```

3. Check Laravel logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

