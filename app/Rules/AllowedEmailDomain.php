<?php

namespace App\Rules;

use App\Helpers\Trans;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class AllowedEmailDomain implements ValidationRule
{
    protected array $allowedDomains;

    public function __construct(string $domains)
    {
        $this->allowedDomains = array_map('trim', explode(',', strtolower($domains)));
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $domain = strtolower(substr(strrchr($value, '@'), 1));

        if (! in_array($domain, $this->allowedDomains)) {
            $fail(Trans::get('api.allowed_email_domain', ['domains' => implode(', ', $this->allowedDomains)]));
        }
    }
}
