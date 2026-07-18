<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . auth()->id(),
            'username' => 'nullable|string|max:255|unique:users,username,' . auth()->id(),
            'bio' => 'nullable|string|max:500',
            'avatar' => ['nullable', 'file', 'mimes:jpeg,jpg,png', 'max:10240'], // max 10MB
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.max' => 'The avatar file may not be greater than 10240 kilobytes.',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, jpg, png.'
        ];
    }
}
