<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePermissionRequest extends StorePermissionRequest
{

    public function rules(): array
    {
        $rules = parent::rules();
        return $rules;
    }
}
