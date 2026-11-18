<?php

namespace App\Http\Requests;

use App\Services\CourierDispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCourierDispatchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierDispatchService::class)->sanitizeInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $extra = array_diff(array_keys($this->all()), ['parcel_id']);

            if ($extra !== []) {
                $validator->errors()->add('input', 'Unrecognized input fields cannot be accepted: '.implode(', ', $extra));
            }
        });
    }
}
