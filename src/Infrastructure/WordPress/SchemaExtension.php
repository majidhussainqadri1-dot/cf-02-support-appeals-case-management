<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use InvalidArgumentException;

final class SchemaExtension
{
    public const VERSION = '1.1.0';

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
