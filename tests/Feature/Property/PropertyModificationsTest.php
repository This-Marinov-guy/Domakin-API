<?php

namespace Tests\Feature\Property;

use App\Http\Controllers\PropertyController;
use App\Models\Property;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyModificationsTest extends TestCase
{
    use DatabaseTransactions;
    use MocksPropertyServices;
    use PropertyControllerData;

    protected function setUp(): void
    {
        try { DB::reconnect(); } catch (\Throwable) {}
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        try { DB::reconnect(); } catch (\Throwable) {}
    }

    // ---------------------------------------------------------------
    // getModifications — GET /api/v1/property/modifications/{id}
    // ---------------------------------------------------------------

    public function test_get_modifications_returns_empty_array_for_new_property(): void
    {
        $property = $this->createPropertyWithRelations();

        $request    = Request::create("/api/v1/property/modifications/{$property->id}", 'GET');
        $controller = app(PropertyController::class);

        $response = $controller->getModifications($request, $property->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertIsArray($payload['data']);
        $this->assertEmpty($payload['data']);
    }

    public function test_get_modifications_returns_404_for_nonexistent_property(): void
    {
        $request    = Request::create('/api/v1/property/modifications/9999999', 'GET');
        $controller = app(PropertyController::class);

        $response = $controller->getModifications($request, 9999999);

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_get_modifications_returns_existing_modifications(): void
    {
        $property = $this->createPropertyWithRelations([
            'modifications' => [
                ['id' => 'uuid-1', 'userId' => null, 'timestamp' => '2026-03-17T10:00:00+00:00', 'content' => 'Property created'],
            ],
        ]);

        $request    = Request::create("/api/v1/property/modifications/{$property->id}", 'GET');
        $controller = app(PropertyController::class);

        $response = $controller->getModifications($request, $property->id);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Property created', $payload['data'][0]['content']);
    }

    // ---------------------------------------------------------------
    // addModification — POST /api/v1/property/modifications/add
    // ---------------------------------------------------------------

    public function test_add_modification_returns_422_when_property_id_missing(): void
    {
        $this->mockUserService();
        $request    = Request::create('/api/v1/property/modifications/add', 'POST', ['content' => 'Test']);
        $controller = app(PropertyController::class);

        $response = $controller->addModification($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_add_modification_returns_422_when_content_missing(): void
    {
        $this->mockUserService();
        $property   = $this->createPropertyWithRelations();
        $request    = Request::create('/api/v1/property/modifications/add', 'POST', ['propertyId' => $property->id]);
        $controller = app(PropertyController::class);

        $response = $controller->addModification($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_add_modification_returns_400_for_nonexistent_property(): void
    {
        $this->mockUserService();
        $request = Request::create('/api/v1/property/modifications/add', 'POST', [
            'propertyId' => 9999999,
            'content'    => 'Test message',
        ]);
        $controller = app(PropertyController::class);

        $response = $controller->addModification($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 400);
        $this->assertFalse($payload['status']);
    }

    public function test_add_modification_appends_entry_with_correct_fields(): void
    {
        $this->mockUserService();
        $property = $this->createPropertyWithRelations();

        $request = Request::create('/api/v1/property/modifications/add', 'POST', [
            'propertyId' => $property->id,
            'content'    => 'Manual note added',
        ]);
        $controller = app(PropertyController::class);

        $response = $controller->addModification($request, app(UserService::class));

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertCount(1, $payload['data']);

        $mod = $payload['data'][0];
        $this->assertSame('Manual note added', $mod['content']);
        $this->assertSame(self::TEST_USER_UUID, $mod['userId']);
        $this->assertArrayHasKey('id', $mod);
        $this->assertArrayHasKey('timestamp', $mod);
    }

    public function test_add_modification_accumulates_multiple_entries(): void
    {
        $this->mockUserService();
        $property   = $this->createPropertyWithRelations();
        $controller = app(PropertyController::class);

        foreach (['First note', 'Second note', 'Third note'] as $note) {
            $request = Request::create('/api/v1/property/modifications/add', 'POST', [
                'propertyId' => $property->id,
                'content'    => $note,
            ]);
            $controller->addModification($request, app(UserService::class));
        }

        $property->refresh();
        $this->assertCount(3, $property->modifications);
        $this->assertSame('First note', $property->modifications[0]['content']);
        $this->assertSame('Third note', $property->modifications[2]['content']);
    }

    // ---------------------------------------------------------------
    // deleteModification — DELETE /api/v1/property/modifications/delete
    // ---------------------------------------------------------------

    public function test_delete_modification_returns_422_when_fields_missing(): void
    {
        $request    = Request::create('/api/v1/property/modifications/delete', 'DELETE', []);
        $controller = app(PropertyController::class);

        $response = $controller->deleteModification($request);

        $payload = $this->assertJsonStatus($response, 422);
        $this->assertFalse($payload['status']);
    }

    public function test_delete_modification_removes_entry_by_id(): void
    {
        $property = $this->createPropertyWithRelations([
            'modifications' => [
                ['id' => 'uuid-keep', 'userId' => null, 'timestamp' => '2026-03-17T10:00:00+00:00', 'content' => 'Keep me'],
                ['id' => 'uuid-del',  'userId' => null, 'timestamp' => '2026-03-17T11:00:00+00:00', 'content' => 'Delete me'],
            ],
        ]);

        $request = Request::create('/api/v1/property/modifications/delete', 'DELETE', [
            'propertyId'     => $property->id,
            'modificationId' => 'uuid-del',
        ]);
        $controller = app(PropertyController::class);

        $response = $controller->deleteModification($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertTrue($payload['status']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Keep me', $payload['data'][0]['content']);
    }

    public function test_delete_modification_with_nonexistent_id_returns_unchanged_list(): void
    {
        $property = $this->createPropertyWithRelations([
            'modifications' => [
                ['id' => 'uuid-a', 'userId' => null, 'timestamp' => '2026-03-17T10:00:00+00:00', 'content' => 'Stay'],
            ],
        ]);

        $request = Request::create('/api/v1/property/modifications/delete', 'DELETE', [
            'propertyId'     => $property->id,
            'modificationId' => 'does-not-exist',
        ]);
        $controller = app(PropertyController::class);

        $response = $controller->deleteModification($request);

        $payload = $this->assertJsonStatus($response, 200);
        $this->assertCount(1, $payload['data']);
    }

    // ---------------------------------------------------------------
    // Auto-appended modifications (create / edit / delete lifecycle)
    // ---------------------------------------------------------------

    public function test_property_create_appends_initial_modification(): void
    {
        Queue::fake();
        $this->mockCreateServices();

        $controller = app(PropertyController::class);
        $request    = Request::create('/api/v1/property/create', 'POST', $this->createRequestData());

        $controller->create(
            $request,
            app(\App\Files\CloudinaryService::class),
            app(\App\Services\GoogleServices\GoogleSheetsService::class),
            app(\App\Services\PropertyService::class),
            app(\App\Services\UserService::class),
            app(\App\Services\Payment\PaymentLinkService::class),
            app(\App\Services\ListingMailerService::class),
        );

        $property = Property::latest('id')->first();
        $this->assertNotNull($property);
        $this->assertCount(1, $property->modifications);
        $this->assertSame('Property created', $property->modifications[0]['content']);
    }
}
