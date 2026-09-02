<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|min:3',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', Password::defaults(), 'confirmed'],
            'username' => 'nullable|string|unique:users'
        ];
    }

    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Name must be a string',
            'name.required' => 'Name is required',
            'email.string' => 'Email must be a string',
            'email.required' => 'Email is required',
            'email.email' => 'Email must be a valid email address',
            'email.unique' => 'Email is already taken',
            'password.required' => 'Password is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.mixedCase' => 'Password must contain at least one uppercase and one lowercase letter',
            'password.uncompromised' => 'Password has been compromised in a data breach, please choose a different password',
            'password.numbers' => 'Password must contain at least one number',
            'password.letters' => 'Password must contain at least one letter',
        ];

    }
}
