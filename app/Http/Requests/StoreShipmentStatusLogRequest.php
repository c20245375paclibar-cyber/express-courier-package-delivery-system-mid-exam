<?php

namespace App\Http\Requests;

use App\Services\CourierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShipmentStatusLogRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierService::class)->sanitizeStatusLogInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
            'status' => ['required', Rule::in(CourierService::SHIPMENT_STATUSES)],
            'current_hub_name' => ['nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleHubName($value)) {
                    $fail('Enter a valid transit hub name.');
                }
            }],
            'occurred_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = ['parcel_id', 'status', 'current_hub_name', 'occurred_at'];

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
            'parcel_id.exists' => 'Select an existing parcel.',
            'status.in' => 'Choose a valid shipment status.',
            'occurred_at.after_or_equal' => 'The status date and time cannot be before today.',
        ];
    }
}
