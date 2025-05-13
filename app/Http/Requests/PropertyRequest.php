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

        //TODO: See if Regex patterns can be used for multiple languages, such as 
        return [
            'id'                        => 'integer|min:1',
            'personalData.name'         => 'required|string',
            'personalData.surname'      => 'required|string',
            'personalData.email'        => 'required|email',
            'personalData.phone'        => 'required|integer',

            'propertyData.city'         => 'required|string',
            'propertyData.address'      => 'required|string',
            'propertyData.size'         => 'required|string',
            'propertyData.period'       => 'required|string',
            'propertyData.rent'         => 'required|string',
            'propertyData.bills'        => 'required|string',
            'propertyData.flatmates'    => 'required|string',
            'propertyData.registration' => 'required|string',
<<<<<<< HEAD
            'propertyData.description'  => 'required|string',
            //'propertyData.description.bg' => 'string|required_without:propertyData.description.en, propertyData.description.gr',// could be useful later
            
=======
            'propertyData.description' => 'required|string',
>>>>>>> main
            'images' => 'required|array',

            'terms' => 'required|array',
            'terms.contact' => 'required|accepted',
            'terms.legals' => 'required|accepted',
        ];
    }
}
