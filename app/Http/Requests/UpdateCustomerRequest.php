<?php

namespace App\Http\Requests;

use App\Services\CustomerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(app(CustomerService::class)->sanitizeInput($this->all()));
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CustomerService::isPlausibleName($value)) {
                    $fail('Enter a valid name using letters and standard name punctuation.');
                }
            }],
            'address' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                if (! CustomerService::isPlausibleAddress($value)) {
                    $fail('Enter a valid address with a location name or street.');
                }
            }, Rule::unique('customers')->where(fn ($query) => $query->where('name', $this->input('name')))->ignore($this->route('customer'))],
        ];
    }
}
