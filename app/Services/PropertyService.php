<?php

namespace App\Services;

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
                'price' => $property['property_data']['rent'],
                'title' => Helpers::getTranslatedValue($property['property_data']['title'], $language, false, 'Available room'),
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

        return $modifiedProperties;
    }
}
