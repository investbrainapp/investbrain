<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;

class FormRequest extends BaseFormRequest
{

    public function requestOrModelValue($key, $model): mixed
    {
        return $this->request->get($key) ?? $this->{$model}?->{$key};
    } 
}