<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use RuntimeException;
use Sabri\CF02\Authorization\PrincipalContext;

/** Idempotent, non-destructive installation and route-contract repair. */
final class RepairService
{
    public static function register(): void
    {
        add_filter('site_status_tests', static function (array $tests): array {
            $tests['direct']['cf02_repair_integrity'] = [
                'label' => __('CF-02 installation integrity', 'cf-02-support-appeals-case-management'),
                'test' => static function (): array {
                    $inspection = self::inspect();
                    $healthy = $inspection['missing_tables'] === []
                        && $inspection['legacy_local_roles_present'] === []
                        && (($inspection['route_contracts']['ready'] ?? false) === true);
                    return [
                        'label' => $healthy ? __('CF-02 installation is consistent', 'cf-02-support-appeals-case-management') : __('CF-02 repair is required', 'cf-02-support-appeals-case-management'),
                        'status' => $healthy ? 'good' : 'critical',
                        'badge' => ['label' => __('CF-02', 'cf-02-support-appeals-case-management'), 'color' => 'blue'],
                        'description' => '<p>' . esc_html(wp_json_encode($inspection)) . '</p>',
                        'actions' => '',
                        'test' => 'cf02_repair_integrity',
                    ];
                },
            ];
            return $tests;
        });
    }
    /** @return array<string,mixed> */
    public static function inspect(): array
    {
        global $wpdb;
        $missingTables = [];
        foreach (Installer::tableNames((string) $wpdb->prefix) as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || $found !== $table) {
                $missingTables[] = $table;
            }
        }
        $receipts = get_option('cf02_route_contract_receipts', []);
        $accepted = is_array($receipts) && count($receipts) === count(RouteRegistrar::contracts());
        if ($accepted) {
            foreach ($receipts as $receipt) {
                if (!is_array($receipt) || ($receipt['accepted'] ?? false) !== true
                    || ($receipt['owner'] ?? null) !== 'File 20') {
                    $accepted = false;
                    break;
                }
            }
        }
        $routeEvidence = apply_filters('cf02_route_contract_reconciliation', [
            'ready' => $accepted,
            'registered_routes' => array_keys(is_array($receipts) ? $receipts : []),
            'owner' => 'File 20',
            'receipts' => is_array($receipts) ? $receipts : [],
        ], RouteRegistrar::contracts());
        return [
            'schema_expected' => SchemaCompletion::VERSION,
            'schema_installed' => Installer::schemaVersion(),
            'missing_tables' => $missingTables,
            'route_contracts' => is_array($routeEvidence) ? $routeEvidence : ['ready' => false],
            'legacy_local_roles_present' => self::legacyRoles(),
            'scheduler' => Scheduler::inspection(),
            'repairable' => true,
            'destructive' => false,
        ];
    }

    /** @return array<string,mixed> */
    public static function repair(PrincipalContext $context, string $approvalRef, DateTimeImmutable $at): array
    {
        if (!$context->hasAnyCapability('configuration.activate', 'release.evidence.read') || !$context->recentlyAuthenticated($at)) {
            throw new RuntimeException('Repair requires File 00 authority and recent authentication.');
        }
        if (preg_match('/^CF02-REPAIR-[A-Za-z0-9_-]{8,64}$/', $approvalRef) !== 1) {
            throw new RuntimeException('A governed repair approval reference is required.');
        }
        Installer::install();
        RoleRegistrar::register();
        do_action('cf02_republish_route_contracts', RouteRegistrar::contracts(), $approvalRef);
        Scheduler::repairRegistration();
        $result = self::inspect();
        $result['approval_ref'] = $approvalRef;
        $result['actor_ref'] = $context->actorReference();
        $result['repaired_at'] = $at->format(DATE_ATOM);
        $result['status'] = $result['missing_tables'] === [] && $result['legacy_local_roles_present'] === [] ? 'repaired' : 'incomplete';
        update_option('cf02_last_repair_evidence', $result, false);
        global $wpdb;
        $json = wp_json_encode($result, JSON_UNESCAPED_SLASHES);
        $wpdb->insert($wpdb->prefix . 'cf02_repair_ledger', [
            'repair_uuid' => 'CF02-REPAIR-' . strtoupper(bin2hex(random_bytes(10))),
            'actor_ref' => $context->actorReference(), 'approval_ref' => $approvalRef,
            'status' => (string) $result['status'], 'evidence_json' => $json,
            'evidence_hash' => hash('sha256', $json), 'repaired_at' => $at->format('Y-m-d H:i:s.u'),
        ]);
        return $result;
    }

    /** @return list<string> */
    private static function legacyRoles(): array
    {
        $found = [];
        foreach (['cf02_support_agent','cf02_specialist_agent','cf02_team_lead','cf02_appeal_reviewer','cf02_liaison','cf02_auditor','cf02_support_manager'] as $role) {
            if (get_role($role) !== null) { $found[] = $role; }
        }
        return $found;
    }
}
