<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name'   => 'sometimes|string|max:100',
            'bio'    => 'nullable|string|max:1000',
            'email'  => ['sometimes','email','max:255', Rule::unique('users')->ignore($userId)],

            
            'avatar' => 'sometimes|file|image|mimes:jpg,jpeg,png,webp|max:2048',

            
            'avatar_url' => [
                'sometimes',
                'nullable',
                'url',
                function ($attr, $value, $fail) {
                    if ($value && !preg_match('#^https?://#i', $value)) {
                        $fail('The avatar_url must start with http:// or https://');
                    }
                },
            ],

            'avatar_remove' => 'sometimes|boolean',
        ];
    }
}
