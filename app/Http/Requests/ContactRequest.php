<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership is checked by ContactPolicy in the controller.
    }

    /** The UI sends tags as "a, b, c"; store them as a clean array. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->tags)) {
            $this->merge([
                'tags' => collect(explode(',', $this->tags))
                    ->map(fn ($tag) => trim($tag))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'card_id' => ['nullable', Rule::exists('cards', 'id')->where('user_id', $this->user()->id)],
            'full_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:15'],
            'tags.*' => ['string', 'max:40'],
            'event' => ['nullable', 'string', 'max:160'],
            'met_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
