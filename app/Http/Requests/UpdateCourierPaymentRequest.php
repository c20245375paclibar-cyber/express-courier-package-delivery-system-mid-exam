<?php

namespace App\Http\Requests;

use App\Services\CourierPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourierPaymentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CourierPaymentService::class)->sanitizeInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parcel_id' => ['required', 'integer', 'exists:parcels,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'payment_status' => ['required', Rule::in(CourierPaymentService::STATUSES)],
            'payment_method' => ['nullable', Rule::in(CourierPaymentService::METHODS)],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
