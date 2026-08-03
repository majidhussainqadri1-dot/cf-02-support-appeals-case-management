<?php

declare(strict_types=1);

namespace Sabri\CF02\Configuration;

use Sabri\CF02\Staffing\StaffingRole;

final class QueueRegistry
{
    /** @return array<string, QueueDefinition> */
    public static function defaults(): array
    {
        $queues = [
            new QueueDefinition('identity', 'Identity Support', ['account_access', 'verification'], ['account_support', 'verification_support', 'privacy_safe_handling'], 'team_lead', 'coverage.identity.v1', 'specialist_agent', true),
            new QueueDefinition('publishing', 'Publishing Support', ['publishing'], ['publishing_support'], 'team_lead', 'coverage.publishing.v1', 'specialist_agent'),
            new QueueDefinition('learning', 'Learning and Entitlements', ['learning_billing'], ['learning_support', 'entitlement_support'], 'team_lead', 'coverage.learning.v1', 'specialist_agent'),
            new QueueDefinition('clinic', 'Clinic and Appointment Support', ['clinic_appointment'], ['clinic_support', 'privacy_safe_handling'], 'team_lead', 'coverage.clinic.v1', 'specialist_agent', true),
            new QueueDefinition('communications', 'Messages and Calls Support', ['messages_calls'], ['communications_support', 'privacy_safe_handling'], 'team_lead', 'coverage.communications.v1', 'specialist_agent', true),
            new QueueDefinition('media', 'Media and PDF Support', ['media_pdf'], ['media_support', 'rights_safety'], 'team_lead', 'coverage.media.v1', 'specialist_agent'),
            new QueueDefinition('marketplace', 'Marketplace Support', ['marketplace'], ['marketplace_support', 'privacy_safe_handling'], 'team_lead', 'coverage.marketplace.v1', 'specialist_agent', true),
            new QueueDefinition('technical', 'Technical and Accessibility Support', ['technical', 'accessibility'], ['technical_support', 'accessibility_support'], 'team_lead', 'coverage.technical.v1', 'specialist_agent'),
            new QueueDefinition('sensitive_liaison', 'Privacy and Safety Liaison', ['privacy_data_rights', 'safety_abuse'], ['privacy_liaison', 'safety_liaison', 'restricted_evidence'], 'sensitive_liaison', 'coverage.sensitive.v1', 'team_lead', true),
        ];

        $indexed = [];
        foreach ($queues as $queue) {
            $indexed[$queue->key()] = $queue;
        }

        return $indexed;
    }

    /** @return list<string> */
    public static function validate(): array
    {
        $reasons = [];
        $taxonomy = SupportTaxonomy::defaults();
        $queues = self::defaults();
        $assignedCategories = [];
        $validRoles = array_map(static fn (StaffingRole $role): string => $role->value, StaffingRole::cases());

        foreach ($queues as $key => $queue) {
            if ($key !== $queue->key()) {
                $reasons[] = sprintf('Queue index mismatch: %s.', $key);
            }

            if (!in_array($queue->ownerRole(), $validRoles, true)) {
                $reasons[] = sprintf('Queue %s has an unknown owner role.', $key);
            }

            if (!in_array($queue->escalationRole(), $validRoles, true)) {
                $reasons[] = sprintf('Queue %s has an unknown escalation role.', $key);
            }

            foreach ($queue->categoryKeys() as $categoryKey) {
                if (!isset($taxonomy[$categoryKey])) {
                    $reasons[] = sprintf('Queue %s references an unknown category: %s.', $key, $categoryKey);
                    continue;
                }

                $category = $taxonomy[$categoryKey];

                if ($category->queueKey() !== $key) {
                    $reasons[] = sprintf('Category %s is assigned to the wrong queue.', $categoryKey);
                }

                if (isset($assignedCategories[$categoryKey])) {
                    $reasons[] = sprintf('Category is assigned to more than one queue: %s.', $categoryKey);
                }

                foreach ($category->requiredSkills() as $skill) {
                    if (!in_array($skill, $queue->requiredSkills(), true)) {
                        $reasons[] = sprintf('Queue %s does not provide category skill %s.', $key, $skill);
                    }
                }

                if ($category->specialistOnly() && !$queue->sensitive()) {
                    $reasons[] = sprintf('Specialist-only category is assigned to a non-sensitive queue: %s.', $categoryKey);
                }

                $assignedCategories[$categoryKey] = true;
            }

            foreach ($queue->requiredSkills() as $skill) {
                if (!SkillCatalog::exists($skill)) {
                    $reasons[] = sprintf('Queue %s references an unknown skill: %s.', $key, $skill);
                }
            }
        }

        foreach (array_keys($taxonomy) as $categoryKey) {
            if (!isset($assignedCategories[$categoryKey])) {
                $reasons[] = sprintf('Category has no queue assignment: %s.', $categoryKey);
            }
        }

        return array_values(array_unique($reasons));
    }
}
