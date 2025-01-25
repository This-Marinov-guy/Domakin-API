<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PropertyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        //TODO: i think we can add regex patterns to reduce XSS vulnerabilities.
        return [
            'personalData.name' => 'required|string',
            'personalData.surname' => 'required|string',
            'personalData.email' => 'required|email',
            'personalData.phone' => 'required|string',

            'propertyData.city' => 'required|string',
            'propertyData.address' => 'required|string',
            'propertyData.size' => 'required|string',
            'propertyData.period' => 'required|string',
            'propertyData.rent' => 'required|string',
            'propertyData.bills' => 'required|string',
            'propertyData.flatmates' => 'required|string',
            'propertyData.registration' => 'required|string',
            'propertyData.description' => 'required|string',
            'images' => 'required|array',

            'terms.contact' => 'required|boolean',
            'terms.legals' => 'required|boolean',
        ];
    }
}
