<?php

namespace App\Classes;

use App\Constants\ErrorMessages;
use Illuminate\Support\Facades\Log;

class ApiResponseClass
{
    // For Common Error response use (so the user does not see the request broken)
    public static function sendError($message = ErrorMessages::GENERAL['message'], $messageTag = ErrorMessages::GENERAL['tag'], $code = 200)
    {
        $response = [
            'status' => false,
            'message' => $message,
            'tag' => $messageTag,
        ];

        return response()->json($response, $code);
    }

    public static function sendInvalidFields($invalidFields = [], $errorMessages = [], $code = 200)
    {
        $errorTags = [];
        $messageTag = ErrorMessages::REQUIRED_FIELDS['tag'];
        $hasRequiredError = false;
        $missingFields = [];

        foreach ($invalidFields as $field => $messages) {
            $tag = null;

            foreach ($messages as $ruleMessage) {
                if (is_string($ruleMessage) && str_contains(strtolower($ruleMessage), 'required')) {
                    $hasRequiredError = true;
                    $missingFields[] = $field; // Collect only missing fields
                }
            }

            // Prioritize specific validation rules
            $priorityRules = ["required", "unique"];

            foreach ($priorityRules as $priority) {
                $ruleKey = "{$field}.{$priority}";
                if (isset($errorMessages[$ruleKey]['tag'])) {
                    $tag = $errorMessages[$ruleKey]['tag'];
                    break; // Stop as soon as a prioritized rule is found
                }
            }

            if (!$tag) {
                foreach ($errorMessages as $rule => $details) {
                    if (str_starts_with($rule, $field) && isset($details['tag'])) {
                        $tag = $details['tag'];
                        break;
                    }
                }
            }

            if ($tag) {
                $errorTags[] = $tag;
            }
        }

        // If there are missing fields, return only those in invalid_fields
        if ($hasRequiredError) {
            return response()->json([
                'status' => false,
                'invalid_fields' => $missingFields,
                'tag' => [$messageTag], 
            ], $code);
        }

        return response()->json([
            'status' => false,
            'invalid_fields' => array_keys($invalidFields), 
            'tag' => array_unique($errorTags),
        ], $code);
    }

    // For Success response 
    public static function sendSuccess($data = [], $message = '', $messageTag = '', $code = 200)
    {
        $response = [
            'status' => true,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if (!empty($messageTag)) {
            $response['tag'] = $messageTag;
        }

        return response()->json($response, $code);
    }
}
