<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

final class SensitiveContentDetector
{
    /** @return list<string> */
    public static function warnings(string $value): array
    {
        $patterns = [
            'password' => '/\b(?:password|passwd)\s*[:=]\s*\S+/iu',
            'otp' => '/\b(?:otp|one[- ]time (?:code|password)|verification code)\s*[:=]\s*\d{4,8}\b/iu',
            'card_security_code' => '/\b(?:cvv|cvc|security code)\s*[:=]\s*\d{3,4}\b/iu',
            'payment_card' => '/\b(?:\d[ -]*?){13,19}\b/u',
            'private_key' => '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/u',
        ];

        $warnings = [];
        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $value) === 1) {
                $warnings[] = $label;
            }
        }

        return $warnings;
    }

    public static function containsProhibitedSecret(string $value): bool
    {
        return self::warnings($value) !== [];
    }
}
