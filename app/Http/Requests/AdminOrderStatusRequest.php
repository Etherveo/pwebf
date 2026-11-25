<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// ORD-12: admin fulfillment may only move dibayar -> diproses and
// diproses -> selesai, optionally recording a courier tracking_number.
class AdminOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', Rule::in(['diproses', 'selesai'])],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ];
    }
}
