<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Swapping cards from someone's public card: which of your own cards to send (optional). */
class SwapCardsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Only one of the visitor's own cards; without one, their account name and email are sent.
            'card_id' => ['nullable', 'uuid', Rule::exists('cards', 'id')->where('user_id', $this->user()->getKey())],
        ];
    }
}
