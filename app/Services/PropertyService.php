<?php

namespace App\Services;

use App\Constants\Properties;
use App\Constants\Translations;
use App\Enums\PropertyStatus;

class PropertyService
{
    public function modifyPropertyDataWithTranslations($data)
    {
        $defaultTranslations = Helpers::getDefaultLocalesObject();

        $values = ['title', 'period', 'bills', 'flatmates', 'description'];

        foreach ($values as $key) {
            if (isset($data[$key])) {
                $data[$key] = json_encode([
                    ...$defaultTranslations,
                    'en' => $data[$key]
                ]);
            } else if ($key === 'title') {
                $data[$key] = json_encode([
                    ...$defaultTranslations,
                    'en' => 'Available room'
                ]);
            }
        }

        return $data;
    }

    public function stringifyPropertyDataWithTranslations($data)
    {
        $values = ['title', 'period', 'bills', 'flatmates', 'description'];

        foreach ($values as $key) {
            if (isset($data[$key])) {
                $data[$key] = json_encode($data[$key], JSON_UNESCAPED_UNICODE);
            }
        }

        return $data;
    }

    public function parseProperties($properties)
    {
        $modifiedProperties = Helpers::decodeJsonKeys($properties, ['property_data.title', 'property_data.bills', 'property_data.description', 'property_data.period', 'property_data.flatmates']);
        $modifiedProperties = Helpers::splitStringKeys($modifiedProperties, ['property_data.images']);

        return $modifiedProperties;
    }

    // Note: this is a temporary mapping until we remove the list from the frontend json
    // TODO: fix translations
    public function parsePropertiesForListing($properties, $language = 'en')
    {
        $modifiedProperties = Helpers::decodeJsonKeys($properties, ['property_data.title', 'property_data.bills', 'property_data.description', 'property_data.period', 'property_data.flatmates']);
        $modifiedProperties = Helpers::splitStringKeys($modifiedProperties, ['property_data.images']);

        foreach ($modifiedProperties as $key => $property) {
            $modifiedProperties[$key] = [
                'id' =>  $property['id'] + 1000, // to avoid collision with the id of the property,
                'status' => PropertyStatus::from($property['status'])->label(),
                'statusCode' => $property['status'],
                'slug' => $property['slug'],
                'price' => $property['property_data']['rent'],
                'title' => Helpers::getTranslatedValue($property['property_data']['title'], $language) ?? 'Available room',
                'city' => $property['property_data']['city'],
                'location' => Helpers::extractStreetName($property['property_data']['address']) . ', ' . $property['property_data']['city'],
                'description' => [
                    'property' => Helpers::getTranslatedValue($property['property_data']['description'], $language),
                    'period' => Helpers::getTranslatedValue($property['property_data']['period'], $language),
                    'bills' => Helpers::getTranslatedValue($property['property_data']['bills'], $language),
                    'flatmates' => Helpers::getTranslatedValue($property['property_data']['flatmates'], $language),
                ],
                'main_image' => $property['property_data']['images'][0] ?? null,
                'images' => array_slice($property['property_data']['images'], 1),
            ];
        }

        foreach ($modifiedProperties as $key => $item) {
            $modifiedProperties[$key]['link'] = $this->getPropertyUrl($item, true, $language);
        }

        return $modifiedProperties;
    }

    /**
     * Create a URL slug from property data (id, city/location, title).
     * Matches frontend createPropertySlug logic for consistency.
     *
     * @param array{id?: int|string, city?: string, location?: string, title?: string} $property
     * @return string
     */
    public function createPropertySlug(array $property): string
    {
        $propertyId = (string) ($property['id'] ?? '');
        $location = $property['city'] ?? $property['location'] ?? '';
        $title = $property['title'] ?? '';

        $urlParts = [$propertyId];

        if ($location !== '') {
            $slugPart = mb_strtolower($location);
            $slugPart = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slugPart);
            $slugPart = preg_replace('/\s+/', '-', $slugPart);
            $urlParts[] = trim($slugPart, '-');
        }

        if ($title !== '') {
            $slugPart = mb_strtolower($title);
            $slugPart = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $slugPart);
            $slugPart = preg_replace('/\s+/', '-', $slugPart);
            $urlParts[] = trim($slugPart, '-');
        }

        $slug = implode('-', $urlParts);

        return (string) preg_replace('/-+/', '-', $slug);
    }

    /**
     * Build the public property page URL (matches frontend getPropertyUrl).
     *
     * @param array{id?: int|string, slug?: string, city?: string, location?: string, title?: string} $property
     * @param bool $useNewFormat Use slug-based URL (true) or id-based (false)
     * @param string|null $lang Language code for prefix; no prefix for "en"
     * @return string Path e.g. "/services/renting/property/cozy-room-amsterdam" or "/nl/services/renting/property/1023"
     */
    public function getPropertyUrl(array $property, bool $useNewFormat = true, ?string $lang = null): string
    {
        $slug = $property['slug'] ?? null;

        if (($slug === null || $slug === '') && $useNewFormat) {
            $slug = $this->createPropertySlug($property);
        }

        // $languagePrefix = ($lang !== null && $lang !== 'en') ? '/' . $lang : '';

        $id = (int)$property['id'] + Properties::FRONTEND_PROPERTY_ID_INDEXING;

        return env('FRONTEND_URL') . '/services/renting/property/' . $slug;
    }

    /**
     * Paginate properties with optional relations and transformation.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @param array $relations
     * @param array $splitKeys
     * @return array
     */
    public function paginateProperties($query, $request, $relations = ['personalData', 'propertyData'], $splitKeys = ['property_data.images'])
    {
        $perPage = (int)($request->get('per_page', 15));
        $page = (int)($request->get('page', 1));
        $paginator = $query
            ->orderByDesc('created_at')
            ->with($relations)
            ->paginate($perPage, ['*'], 'page', $page);
        $items = $paginator->items();
        $items = array_map(function ($item) {
            return is_array($item) ? $item : $item->toArray();
        }, $items);
        $items = Helpers::splitStringKeys($items, $splitKeys);
        return [
            'properties' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
