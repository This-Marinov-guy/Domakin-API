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
            'personalData.name' => ['required', 'string', 'regex:/^[a-zA-Z\']+$/'], //Vladi, here is an example of a regex pattern validation. This particular pattern checks if the etered name contains only lower case letters, upper case letters, appostrophies, and any length of input 
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
            'propertyData.description.bg' => 'string|required_without:propertyData.description.en, propertyData.description.gr',
            'propertyData.description.en' => 'string|required_without:propertyData.description.bg, propertyData.description.gr',
            'propertyData.description.gr' => 'string|required_without:propertyData.description.en, propertyData.description.bg',
            
            'images' => 'required|array',
            
            'terms.contact' => 'required|boolean',
            'terms.legals' => 'required|boolean',
        ];
    }
}
