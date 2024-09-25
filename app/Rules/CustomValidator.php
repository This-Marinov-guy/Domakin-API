<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class CustomValidator implements Rule
{
    protected $rules;
    protected $errors = [];

    public function __construct(array $rules)
    {
        $this->rules = $rules;
    }

    public function passes($attribute, $value)
    {
        $validator = Validator::make($value, $this->rules);

        if ($validator->fails()) {
            $this->errors = $validator->errors()->toArray();
            return false;
        }

        return true;
    }

    public function message()
    {
        return $this->errors;
    }
}
