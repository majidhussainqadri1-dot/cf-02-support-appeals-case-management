<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

final class SensitiveContentDetector
{
    /** @return list<string> */
    public static function warnings(string $value): array
    {
        $patterns = [
            // Accept the common "label: secret", "label=secret" and "label secret"
            // forms while requiring a credential-shaped value so ordinary prose such
            // as "password reset" does not become a false positive.
            'password' => '/\b(?:password|passwd)(?:\s*[:=]\s*|\s+)(?=\S{6,})(?=\S*(?:\d|[^\p{L}\p{N}]))\S+/iu',
            'otp' => '/\b(?:otp|one[- ]time (?:code|password)|verification code)(?:\s*[:=]\s*|\s+)\d{4,8}\b/iu',
            'card_security_code' => '/\b(?:cvv|cvc|security code)(?:\s*[:=]\s*|\s+)\d{3,4}\b/iu',
            'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/u',
        ];

        $warnings = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $warnings[] = $label;
            }
        }

        if (self::containsLuhnValidCardNumber($value)) {
            $warnings[] = 'payment_card';
        }

        return array_values(array_unique($warnings));
    }

    public static function containsProhibitedSecret(string $value): bool
    {
        return self::warnings($value) !== [];
    }

    private static function containsLuhnValidCardNumber(string $value): bool
    {
        preg_match_all('/(?<!\d)(?:\d[ -]?){13,19}(?!\d)/u', $value, $matches);

        foreach ($matches[0] ?? [] as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate);
            if (is_string($digits) && strlen($digits) >= 13 && strlen($digits) <= 19 && self::passesLuhn($digits)) {
                return true;
            }
        }

        return false;
    }

    private static function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $double = false;

        for ($index = strlen($digits) - 1; $index >= 0; --$index) {
            $number = (int) $digits[$index];
            if ($double) {
                $number *= 2;
                if ($number > 9) {
                    $number -= 9;
                }
            }
            $sum += $number;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }
}
