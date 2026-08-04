<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use InvalidArgumentException;

final class SchemaExtension
{
    public const VERSION = '1.2.0';

    /** @return array<string, string> */
    public static function statements(string $prefix, string $charsetCollate): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid WordPress table prefix.');
        }
        return [
            'intake_replay' => "CREATE TABLE {$prefix}cf02_intake_replay (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                idempotency_key varchar(128) NOT NULL,
                requester_ref varchar(191) NOT NULL,
                payload_hash char(64) NOT NULL,
                case_uuid char(41) NOT NULL,
                trace_id varchar(40) NOT NULL,
                created_at datetime(6) NOT NULL,
                expires_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY idempotency_key (idempotency_key),
                KEY requester_created (requester_ref,created_at),
                KEY expires_at (expires_at)
            ) {$charsetCollate};",
            'tasks' => "CREATE TABLE {$prefix}cf02_tasks (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                task_uuid varchar(64) NOT NULL,
                case_uuid char(41) NOT NULL,
                task_type varchar(64) NOT NULL,
                assignee_ref varchar(191) NULL,
                dependency_ref varchar(191) NULL,
                state varchar(24) NOT NULL,
                outcome_ref varchar(191) NULL,
                due_at datetime(6) NULL,
                record_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY task_uuid (task_uuid),
                KEY case_state (case_uuid,state),
                KEY assignee_due (assignee_ref,due_at)
            ) {$charsetCollate};",
            'appeal_dossiers' => "CREATE TABLE {$prefix}cf02_appeal_dossiers (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                dossier_uuid varchar(64) NOT NULL,
                appeal_uuid varchar(64) NOT NULL,
                original_decision_ref varchar(191) NOT NULL,
                original_decision_hash char(64) NOT NULL,
                policy_version varchar(64) NOT NULL,
                evidence_refs_json longtext NOT NULL,
                submissions_json longtext NOT NULL,
                dossier_hash char(64) NOT NULL,
                record_version bigint(20) unsigned NOT NULL DEFAULT 1,
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY dossier_uuid (dossier_uuid),
                UNIQUE KEY appeal_uuid (appeal_uuid),
                KEY original_decision_ref (original_decision_ref)
            ) {$charsetCollate};",
            'quality_reviews' => "CREATE TABLE {$prefix}cf02_quality_reviews (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                review_uuid varchar(64) NOT NULL,
                case_uuid char(41) NOT NULL,
                reviewer_ref varchar(191) NOT NULL,
                sample_basis varchar(24) NOT NULL,
                scores_json text NOT NULL,
                findings_hash char(64) NOT NULL,
                identity_suppressed tinyint(1) NOT NULL DEFAULT 1,
                appealed tinyint(1) NOT NULL DEFAULT 0,
                correction_ref varchar(191) NULL,
                reviewed_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY review_uuid (review_uuid),
                KEY case_reviewed (case_uuid,reviewed_at),
                KEY sample_reviewed (sample_basis,reviewed_at)
            ) {$charsetCollate};",
            'feedback' => "CREATE TABLE {$prefix}cf02_feedback (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                case_uuid char(41) NOT NULL,
                respondent_pseudonym_hash char(64) NOT NULL,
                rating tinyint unsigned NULL,
                comment_ciphertext longtext NULL,
                opted_out tinyint(1) NOT NULL DEFAULT 0,
                submitted_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY case_respondent (case_uuid,respondent_pseudonym_hash),
                KEY submitted_at (submitted_at)
            ) {$charsetCollate};",
            'command_payloads' => "CREATE TABLE {$prefix}cf02_command_payloads (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                command_uuid varchar(64) NOT NULL,
                payload_ciphertext longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                created_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY command_uuid (command_uuid)
            ) {$charsetCollate};",
            'outbox_payloads' => "CREATE TABLE {$prefix}cf02_outbox_payloads (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                message_uuid varchar(64) NOT NULL,
                payload_ciphertext longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                created_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY message_uuid (message_uuid)
            ) {$charsetCollate};",
            'events' => "CREATE TABLE {$prefix}cf02_events (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_uuid varchar(64) NOT NULL,
                aggregate_type varchar(32) NOT NULL,
                aggregate_ref varchar(191) NOT NULL,
                event_type varchar(80) NOT NULL,
                actor_ref varchar(191) NOT NULL,
                actor_role varchar(64) NOT NULL,
                purpose varchar(64) NOT NULL,
                payload_json longtext NOT NULL,
                payload_hash char(64) NOT NULL,
                idempotency_key varchar(128) NOT NULL,
                publish_state varchar(24) NOT NULL DEFAULT 'pending',
                publish_attempts smallint unsigned NOT NULL DEFAULT 0,
                next_attempt_at datetime(6) NULL,
                previous_hash char(64) NULL,
                event_hash char(64) NOT NULL,
                occurred_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY event_uuid (event_uuid),
                UNIQUE KEY idempotency_event (idempotency_key,event_type),
                KEY aggregate_timeline (aggregate_type,aggregate_ref,occurred_at),
                KEY publish_retry (publish_state,next_attempt_at)
            ) {$charsetCollate};",
            'representatives' => "CREATE TABLE {$prefix}cf02_representatives (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                grant_uuid varchar(64) NOT NULL,
                requester_ref varchar(191) NOT NULL,
                representative_ref varchar(191) NOT NULL,
                authority_type varchar(40) NOT NULL,
                scope_json text NOT NULL,
                native_owner varchar(32) NOT NULL,
                native_authority_ref varchar(191) NOT NULL,
                verified_at datetime(6) NOT NULL,
                expires_at datetime(6) NOT NULL,
                revoked_at datetime(6) NULL,
                record_version bigint(20) unsigned NOT NULL DEFAULT 1,
                PRIMARY KEY (id),
                UNIQUE KEY grant_uuid (grant_uuid),
                KEY representative_active (representative_ref,expires_at,revoked_at),
                KEY requester_active (requester_ref,expires_at,revoked_at)
            ) {$charsetCollate};",
            'case_links' => "CREATE TABLE {$prefix}cf02_case_links (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                link_uuid varchar(64) NOT NULL,
                case_uuid char(41) NOT NULL,
                owner_key varchar(64) NOT NULL,
                object_type varchar(64) NOT NULL,
                object_ref varchar(191) NOT NULL,
                object_version varchar(64) NOT NULL,
                privacy_class char(2) NOT NULL,
                projection_hash char(64) NOT NULL,
                state varchar(24) NOT NULL DEFAULT 'active',
                created_at datetime(6) NOT NULL,
                updated_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY link_uuid (link_uuid),
                UNIQUE KEY owner_object_case (owner_key,object_type,object_ref,case_uuid),
                KEY case_state (case_uuid,state)
            ) {$charsetCollate};",
            'inbound_receipts' => "CREATE TABLE {$prefix}cf02_inbound_receipts (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                receipt_uuid varchar(64) NOT NULL,
                source_owner varchar(64) NOT NULL,
                external_event_id varchar(191) NOT NULL,
                channel varchar(24) NOT NULL,
                sender_ref varchar(191) NOT NULL,
                sender_trust varchar(24) NOT NULL,
                signature_hash char(64) NOT NULL,
                payload_hash char(64) NOT NULL,
                case_uuid char(41) NULL,
                received_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY receipt_uuid (receipt_uuid),
                UNIQUE KEY source_external (source_owner,external_event_id),
                KEY case_received (case_uuid,received_at)
            ) {$charsetCollate};",
            'attachment_tokens' => "CREATE TABLE {$prefix}cf02_attachment_tokens (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                token_hash char(64) NOT NULL,
                attachment_uuid varchar(64) NOT NULL,
                actor_ref varchar(191) NOT NULL,
                purpose varchar(64) NOT NULL,
                expires_at datetime(6) NOT NULL,
                used_at datetime(6) NULL,
                created_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY token_hash (token_hash),
                KEY attachment_expiry (attachment_uuid,expires_at)
            ) {$charsetCollate};",
            'merge_redirects' => "CREATE TABLE {$prefix}cf02_merge_redirects (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                source_case_uuid char(41) NOT NULL,
                target_case_uuid char(41) NOT NULL,
                reason varchar(255) NOT NULL,
                actor_ref varchar(191) NOT NULL,
                active tinyint(1) NOT NULL DEFAULT 1,
                reversal_ref varchar(191) NULL,
                created_at datetime(6) NOT NULL,
                reversed_at datetime(6) NULL,
                PRIMARY KEY (id),
                KEY source_active (source_case_uuid,active),
                KEY target_active (target_case_uuid,active)
            ) {$charsetCollate};",
            'incident_links' => "CREATE TABLE {$prefix}cf02_incident_links (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                incident_ref varchar(191) NOT NULL,
                case_uuid char(41) NOT NULL,
                link_role varchar(32) NOT NULL,
                public_status varchar(40) NOT NULL,
                native_owner varchar(32) NOT NULL,
                linked_at datetime(6) NOT NULL,
                unlinked_at datetime(6) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY incident_case (incident_ref,case_uuid),
                KEY case_active (case_uuid,unlinked_at)
            ) {$charsetCollate};",
            'metrics' => "CREATE TABLE {$prefix}cf02_metrics (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                metric_uuid varchar(64) NOT NULL,
                metric_type varchar(64) NOT NULL,
                dimension_hash char(64) NOT NULL,
                payload_json longtext NOT NULL,
                identity_threshold smallint unsigned NOT NULL DEFAULT 5,
                observed_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY metric_uuid (metric_uuid),
                KEY metric_observed (metric_type,observed_at)
            ) {$charsetCollate};",
            'note_revisions' => "CREATE TABLE {$prefix}cf02_note_revisions (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                message_uuid varchar(64) NOT NULL,
                revision bigint(20) unsigned NOT NULL,
                editor_ref varchar(191) NOT NULL,
                body_ciphertext longtext NOT NULL,
                body_hash char(64) NOT NULL,
                edited_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY message_revision (message_uuid,revision),
                KEY edited_at (edited_at)
            ) {$charsetCollate};",
            'retention_ledger' => "CREATE TABLE {$prefix}cf02_retention_ledger (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                object_type varchar(64) NOT NULL,
                object_ref varchar(191) NOT NULL,
                policy_version varchar(64) NOT NULL,
                action_key varchar(32) NOT NULL,
                provider_results_json longtext NOT NULL,
                evidence_hash char(64) NOT NULL,
                executed_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY object_action (object_type,object_ref,action_key,evidence_hash),
                KEY executed_at (executed_at)
            ) {$charsetCollate};",
        ];
    }
}
