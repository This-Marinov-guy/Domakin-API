<?php

namespace App\Services;

use App\Constants\Translations;
use App\Enums\PropertyStatus;

class PropertyService
{
    public function modifyPropertyDataWithTranslations($data)
    {
        $helpers = new Helpers();
        $defaultTranslations = $helpers->getDefaultLocalesObject();

        $values = ['period', 'bills', 'flatmates', 'description'];

        foreach ($values as $key) {
            if (isset($data[$key])) {
                $data[$key] = json_encode([
                    ...$defaultTranslations,
                    'en' => $data[$key]
                ]);
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
    public function parsePropertiesForListing($properties)
    {
        $modifiedProperties = Helpers::decodeJsonKeys($properties, ['property_data.title', 'property_data.bills', 'property_data.description', 'property_data.period', 'property_data.flatmates']);
        $modifiedProperties = Helpers::splitStringKeys($modifiedProperties, ['property_data.images']);

        foreach ($modifiedProperties as $key => $property) {
            $modifiedProperties[$key] = [
                'id' =>  $property['id'] + 1000, // to avoid collision with the id of the property,
                'status' => PropertyStatus::from($property['status'])->label(),
                'statusCode' => $property['status'],
                'price' => $property['property_data']['rent'],
                'title' => $property['property_data']['title']['en'],
                'city' => $property['property_data']['city'],
                'location' => Helpers::extractStreetName($property['property_data']['address']),
                'description' => [
                    'property' => $property['property_data']['description']['en'],
                    'period' => $property['property_data']['period']['en'],
                    'bills' => $property['property_data']['bills']['en'],
                    'flatmates' => $property['property_data']['flatmates']['en']
                ],
                'main_image' => $property['property_data']['images'][0] ?? null,
                'images' => array_slice($property['property_data']['images'], 1),
            ];
        }

        return $modifiedProperties;
    }
}
