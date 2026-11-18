<?php

namespace App\Http\Requests;

use App\Services\CourierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreParcelRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierService::class)->sanitizeParcelInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['required', 'string', 'max:50', 'unique:parcels,tracking_number'],
            'sender_name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleName($value)) {
                    $fail('Enter a valid name using letters and standard name punctuation.');
                }
            }],
            'sender_address' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleAddress($value)) {
                    $fail('Enter a valid address with a location name or street.');
                }
            }],
            'recipient_name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleName($value)) {
                    $fail('Enter a valid name using letters and standard name punctuation.');
                }
            }],
            'recipient_address' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleAddress($value)) {
                    $fail('Enter a valid address with a location name or street.');
                }
            }],
            'parcel_weight_kg' => ['required', 'numeric', 'gt:0', 'max:99999.999'],
            'delivery_type' => ['required', Rule::in(CourierService::DELIVERY_TYPES)],
            'current_hub_name' => ['nullable', 'string', 'max:120', function ($attribute, $value, $fail) {
                if (! CourierService::isPlausibleHubName($value)) {
                    $fail('Enter a valid transit hub name.');
                }
            }],
            'initial_status' => ['required', Rule::in(CourierService::SHIPMENT_STATUSES)],
            'status_occurred_at' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $allowed = [
            'tracking_number',
            'sender_name',
            'sender_address',
            'recipient_name',
            'recipient_address',
            'parcel_weight_kg',
            'delivery_type',
            'current_hub_name',
            'initial_status',
            'status_occurred_at',
        ];

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
            'tracking_number.unique' => 'This tracking number is already in use.',
            'parcel_weight_kg.gt' => 'Parcel weight must be greater than zero.',
            'delivery_type.in' => 'Choose standard, express, or overnight delivery.',
            'initial_status.in' => 'Choose a valid initial shipment status.',
            'status_occurred_at.after_or_equal' => 'The status date and time cannot be before today.',
        ];
    }
}
