<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

/** Public lead form on a card page. Submitted by anonymous visitors. */
class LeadCaptureRequest extends FormRequest
{
    use ValidatesPhoneNumbers;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneNumbers(['phone']);
    }

    public function messages(): array
    {
        return ['phone' => 'Enter a valid phone number for the selected country.'];
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['required_without:email', ...$this->phoneRules()],
            'company' => ['nullable', 'string', 'max:120'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
