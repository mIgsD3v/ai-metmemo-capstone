<?php

namespace App\Http\Requests\Concerns;

use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;
use Throwable;

/**
 * Mobile and telephone numbers are checked against their country's numbering plan and stored the same way
 * everywhere: "+63 917 123 4567". A number without a country code is read as a Philippine one.
 */
trait ValidatesPhoneNumbers
{
    public const DEFAULT_PHONE_COUNTRY = 'PH';

    /** @return array<int, mixed> */
    protected function phoneRules(): array
    {
        return ['nullable', 'string', 'max:30', (new Phone)->international()->country(self::DEFAULT_PHONE_COUNTRY)];
    }

    /** @param  array<int, string>  $fields */
    protected function normalizePhoneNumbers(array $fields): void
    {
        foreach ($fields as $field) {
            $value = $this->input($field);

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            try {
                $phone = new PhoneNumber($value, self::DEFAULT_PHONE_COUNTRY);

                if ($phone->isValid()) {
                    $this->merge([$field => $phone->formatInternational()]);
                }
            } catch (Throwable) {
                // Left as typed: the rule reports it.
            }
        }
    }

    /** @return array<string, string> */
    protected function phoneMessages(): array
    {
        return [
            'phone' => 'Enter a valid mobile number for the selected country.',
            'telephone' => 'Enter a valid telephone number for the selected country.',
        ];
    }
}
