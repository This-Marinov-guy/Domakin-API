<?php

namespace App\Services;

use App\Constants\Translations;

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
        $modifiedProperties = Helpers::decodeJsonKeys($properties, ['property_data.bills', 'property_data.description', 'property_data.period', 'property_data.flatmates']);
        $modifiedProperties = Helpers::splitStringKeys($modifiedProperties, ['property_data.images']);

        return $modifiedProperties;
    }
}
