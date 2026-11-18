<?php

namespace App\Http\Requests;

use App\Services\TransitHubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransitHubRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(TransitHubService::class)->sanitizeInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120', Rule::unique('transit_hubs', 'name')->ignore($this->route('transit_hub')), function ($attribute, $value, $fail) {
            if (! TransitHubService::isPlausibleName($value)) {
                $fail('Enter a valid transit hub name.');
            }
        }]];
    }
}
