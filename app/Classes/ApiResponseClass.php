<?php

namespace App\Classes;

use App\Constants\ErrorMessages;

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

    // For Error invalid fields response
    public static function sendInvalidFields($invalidFields = [], $message = ErrorMessages::REQUIRED_FIELDS['message'], $messageTag = ErrorMessages::REQUIRED_FIELDS['tag'], $code = 200)
    {
        $response = [
            'status' => false,
            'invalid_fields' => $invalidFields,
            'message' => $message,
            'tag' => $messageTag,
        ];

        return response()->json($response, $code);
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
