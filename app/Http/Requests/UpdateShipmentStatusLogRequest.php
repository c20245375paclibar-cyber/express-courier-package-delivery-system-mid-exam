<?php

namespace App\Http\Requests;

use App\Services\ShipmentStatusLogService;
use App\Services\TransitHubService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateShipmentStatusLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(ShipmentStatusLogService::class)->sanitizeInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ShipmentStatusLogService::STATUSES)],
            'current_hub_name' => ['nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (! TransitHubService::isPlausibleName($value)) {
                    $fail('Enter a valid transit hub name.');
                }
            }],
            'occurred_at' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = ['status', 'current_hub_name', 'occurred_at'];

        $validator->after(function (Validator $validator) use ($allowed): void {
            $extra = array_diff(array_keys($this->all()), $allowed);

            if ($extra !== []) {
                $validator->errors()->add('input', 'Unrecognized input fields cannot be accepted: '.implode(', ', $extra));
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Choose a valid shipment status.',
            'occurred_at.required' => 'A date and time is required for this status update.',
            'occurred_at.after_or_equal' => 'The status date and time cannot be before today.',
        ];
    }
}
