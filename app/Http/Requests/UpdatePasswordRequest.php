<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', Password::defaults(), 'string'],
            'new_password' => ['required', Password::defaults(), 'string', 'confirmed', 'string', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Current password is required.',
            'new_password.required' => 'New password is required.',
            'new_password.string' => 'New password must be a string.',
            'new_password.confirmed' => 'New password and confirmation does not match.',
            'new_password.different' => 'New password must be different from current password.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
