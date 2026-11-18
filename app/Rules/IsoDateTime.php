<?php

namespace App\Rules;

use App\Support\Time;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/** Accepts only ISO-8601 date-times that carry a timezone, e.g. 2030-01-07T04:00:00Z. */
class IsoDateTime implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = "The {$attribute} must be an ISO 8601 date-time in UTC, e.g. 2030-01-07T04:00:00Z.";

        if (! is_string($value) || ! preg_match(Time::ISO_PATTERN, $value)) {
            $fail($message);

            return;
        }
        // Reject impossible dates such as 2030-02-30 (the regex only checks the shape).
        [$y, $m, $d] = array_map('intval', explode('-', substr($value, 0, 10)));
        if (! checkdate($m, $d, $y)) {
            $fail($message);

            return;
        }
        try {
            Time::parseUtc($value);
        } catch (Throwable) {
            $fail($message);
        }
    }
}
