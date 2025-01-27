<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransactionRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {

        $rules = [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'wishlist' => ['sometimes', 'nullable', 'boolean'],
        ];

        if (!is_null($this->portfolio)) {
            $rules['title'][0] = 'sometimes';
        } 

        return $rules;
    }
}
