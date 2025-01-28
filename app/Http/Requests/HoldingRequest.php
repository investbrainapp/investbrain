<?php

namespace App\Http\Requests;

use App\Http\Requests\FormRequest;

class HoldingRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $rules = [
            'reinvest_dividends' => ['sometimes', 'boolean']
        ];

        return $rules;
    }
}
