<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

final class SupportTaxonomy
{
    /** @return array<string, SupportCategory> */
    public static function defaults(): array
    {
        $categories = [
            new SupportCategory('account_access', 'Account and Access', 'identity', ['account_support', 'privacy_safe_handling'], 'C3', 'Files 00/02', ['issue_type', 'account_reference', 'contact_method']),
            new SupportCategory('verification', 'Verification', 'identity', ['verification_support', 'privacy_safe_handling'], 'C3', 'File 09', ['verification_reference', 'issue_type', 'requested_outcome']),
            new SupportCategory('publishing', 'Publishing', 'publishing', ['publishing_support'], 'C2', 'Files 21/22/23', ['content_reference', 'workflow_state', 'issue_type']),
            new SupportCategory('learning_billing', 'Learning and Billing', 'learning', ['learning_support', 'entitlement_support'], 'C3', 'File 05 / approved entitlement owner', ['product_reference', 'entitlement_reference', 'issue_type']),
            new SupportCategory('clinic_appointment', 'Clinic and Appointment', 'clinic', ['clinic_support', 'privacy_safe_handling'], 'C3', 'File 08', ['clinic_reference', 'appointment_reference', 'issue_type']),
            new SupportCategory('messages_calls', 'Messages and Calls', 'communications', ['communications_support', 'privacy_safe_handling'], 'C3', 'File 17', ['conversation_reference', 'issue_type', 'safety_indicator']),
            new SupportCategory('media_pdf', 'Media and PDF', 'media', ['media_support', 'rights_safety'], 'C2', 'Files 10/11/12', ['media_reference', 'issue_type', 'device_context']),
            new SupportCategory('marketplace', 'Marketplace', 'marketplace', ['marketplace_support', 'privacy_safe_handling'], 'C3', 'File 18', ['listing_reference', 'transaction_reference', 'issue_type']),
            new SupportCategory('privacy_data_rights', 'Privacy and Data Rights', 'sensitive_liaison', ['privacy_liaison', 'restricted_evidence'], 'C4', 'File 24 / native privacy owner', ['request_type', 'identity_verification_reference', 'scope'], true),
            new SupportCategory('safety_abuse', 'Safety and Abuse', 'sensitive_liaison', ['safety_liaison', 'restricted_evidence'], 'C4', 'Relevant native safety owner', ['report_type', 'subject_reference', 'immediacy'], true),
            new SupportCategory('accessibility', 'Accessibility', 'technical', ['accessibility_support', 'technical_support'], 'C2', 'Files 20/25', ['route', 'assistive_technology', 'issue_type']),
            new SupportCategory('technical', 'Technical Fault', 'technical', ['technical_support'], 'C2', 'Platform operations', ['route', 'device_context', 'reproduction_steps']),
        ];

        $indexed = [];
        foreach ($categories as $category) {
            $indexed[$category->key()] = $category;
        }

        return $indexed;
    }

    /** @return list<string> */
    public static function validate(): array
    {
        $reasons = [];
        $categories = self::defaults();

        foreach ($categories as $key => $category) {
            if ($key !== $category->key()) {
                $reasons[] = sprintf('Taxonomy index mismatch: %s.', $key);
            }
        }

        return $reasons;
    }
}
