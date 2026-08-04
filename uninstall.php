<?php

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * CF-02 uninstall is deliberately non-destructive.
 *
 * Support cases, appeals, evidence, retention records, audit history, configuration,
 * migration mappings and owner-contract evidence are preserved. Destructive erasure
 * must be performed through a separately authorized, auditable lifecycle procedure
 * that can respect legal/appeal holds, backups and downstream reconciliation.
 */
foreach ([
    'cf02_process_outbox',
    'cf02_process_events',
    'cf02_process_sla',
    'cf02_process_retention',
    'cf02_process_reconciliation',
] as $hook) {
    wp_clear_scheduled_hook($hook);
}

foreach ([
    'cf02_activation_lock',
    'cf02_installer_lock',
    'cf02_migration_runtime_lock',
    'cf02_repair_runtime_lock',
] as $transient) {
    delete_transient($transient);
    delete_site_transient($transient);
}

// Preserve all canonical and evidentiary data by default.
// The uninstall path performs scheduler and ephemeral-lock cleanup only.
