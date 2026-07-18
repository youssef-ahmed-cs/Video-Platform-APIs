<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'file', 'mimes:jpeg,jpg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'The avatar file may not be greater than 10240 kilobytes.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png.'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
