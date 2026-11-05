<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPhoneNumbers;
use Illuminate\Foundation\Http\FormRequest;

class CardRequest extends FormRequest
{
    use ValidatesPhoneNumbers;

    public function authorize(): bool
    {
        return true; // Ownership is checked by CardPolicy in the controller.
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhoneNumbers(['phone', 'telephone']);
    }

    public function messages(): array
    {
        return $this->phoneMessages();
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => $this->phoneRules(),
            'telephone' => $this->phoneRules(),
            'website' => ['nullable', 'url', 'max:255'],
            'location' => ['nullable', 'string', 'max:160'],
            'is_public' => ['boolean'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_photo' => ['boolean'],
        ];
    }
}
