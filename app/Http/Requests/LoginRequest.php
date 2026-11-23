<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Unified login form with two actors: pembeli | admin_toko.
// Role checks stay in the controller so a wrong-actor attempt always returns
// the same generic credential error as a wrong password.
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'aktor' => ['required', 'string', 'in:pembeli,admin_toko'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string'],
        ];
    }
}
