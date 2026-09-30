<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationPingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('attendance.self');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'pings' => ['required', 'array', 'min:1', 'max:'.config('tracking.max_batch')],
            'pings.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'pings.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'pings.*.accuracy' => ['nullable', 'numeric', 'min:0'],
            'pings.*.speed' => ['nullable', 'numeric'],
            'pings.*.battery' => ['nullable', 'integer', 'between:0,100'],
            'pings.*.t' => ['nullable', 'integer'],
        ];
    }
}
