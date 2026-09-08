<?php

declare(strict_types=1);

namespace Sabri\CF02\Safety;

use InvalidArgumentException;

/**
 * Public-safe routing metadata only. Sensitive operational playbooks, contacts,
 * credentials and incident details must remain outside the public repository.
 */
final class EmergencyRunbookRegistry
{
    /** @var array<string,array{owner:string,mode:string,ordinary_sla:bool,auto_close:bool}> */
    private const RUNBOOKS = [
        'clinical_red_flag' => ['owner' => 'clinical', 'mode' => 'immediate_local_qualified_care_diversion', 'ordinary_sla' => false, 'auto_close' => false],
        'imminent_harm' => ['owner' => 'security_assurance', 'mode' => 'immediate_safety_containment_and_on_call_escalation', 'ordinary_sla' => false, 'auto_close' => false],
        'account_takeover' => ['owner' => 'identity', 'mode' => 'identity_safe_recovery_escalation', 'ordinary_sla' => false, 'auto_close' => false],
        'child_safety' => ['owner' => 'security_assurance', 'mode' => 'age_appropriate_safety_escalation', 'ordinary_sla' => false, 'auto_close' => false],
        'privacy_breach' => ['owner' => 'security_assurance', 'mode' => 'privacy_incident_containment_and_specialist_escalation', 'ordinary_sla' => false, 'auto_close' => false],
        'financial_fraud' => ['owner' => 'payments', 'mode' => 'financial_fraud_specialist_escalation', 'ordinary_sla' => false, 'auto_close' => false],
    ];

    /** @return array{owner:string,mode:string,ordinary_sla:bool,auto_close:bool} */
    public static function forType(string $type): array
    {
        if (!isset(self::RUNBOOKS[$type])) {
            throw new InvalidArgumentException('Unknown CF-02 emergency runbook type.');
        }
        return self::RUNBOOKS[$type];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::RUNBOOKS);
    }

    public static function classifyText(string $text): ?string
    {
        $text = strtolower(trim($text));
        if ($text === '') {
            return null;
        }
        $patterns = [
            'clinical_red_flag' => '/\b(heart attack|unconscious|severe bleeding|stroke|medical emergency|دل کا دورہ|بے ہوش|شدید خون)\b/u',
            'imminent_harm' => '/\b(suicide|kill myself|kill someone|imminent harm|خودکشی|جان سے مار)\b/u',
            'account_takeover' => '/\b(account takeover|account hacked|hijacked account|اکاؤنٹ ہیک)\b/u',
            'child_safety' => '/\b(child abuse|minor exploitation|child safety|بچے.*زیادتی|نابالغ.*خطر)\b/u',
            'privacy_breach' => '/\b(data breach|privacy breach|leaked personal data|ڈیٹا لیک|پرائیویسی بریچ)\b/u',
            'financial_fraud' => '/\b(financial fraud|fraudulent charge|payment fraud|مالی فراڈ|جعلی ادائیگی)\b/u',
        ];
        foreach ($patterns as $type => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $type;
            }
        }
        return null;
    }
}
