<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

final class Scheduler
{
    public const HOOK_OUTBOX = 'cf02_process_outbox';
    public const HOOK_RETENTION = 'cf02_process_retention';
    public const HOOK_RECONCILIATION = 'cf02_process_reconciliation';

    public static function register(): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['cf02_five_minutes'] = [
                'interval' => 300,
                'display' => __('Every five minutes (CF-02)', 'cf-02-support-appeals-case-management'),
            ];
            return $schedules;
        });

        if (!wp_next_scheduled(self::HOOK_OUTBOX)) {
            wp_schedule_event(time() + 60, 'cf02_five_minutes', self::HOOK_OUTBOX);
        }
        if (!wp_next_scheduled(self::HOOK_RECONCILIATION)) {
            wp_schedule_event(time() + 120, 'cf02_five_minutes', self::HOOK_RECONCILIATION);
        }
        if (!wp_next_scheduled(self::HOOK_RETENTION)) {
            wp_schedule_event(time() + 300, 'daily', self::HOOK_RETENTION);
        }

        add_action(self::HOOK_OUTBOX, static function (): void {
            do_action('cf02_outbox_worker_tick', ['limit' => 100, 'deadline_seconds' => 20]);
        });
        add_action(self::HOOK_RECONCILIATION, static function (): void {
            do_action('cf02_reconciliation_worker_tick', ['limit' => 100, 'deadline_seconds' => 20]);
        });
        add_action(self::HOOK_RETENTION, static function (): void {
            do_action('cf02_retention_worker_tick', ['limit' => 250, 'deadline_seconds' => 30]);
        });
    }

    public static function unschedule(): void
    {
        foreach ([self::HOOK_OUTBOX, self::HOOK_RECONCILIATION, self::HOOK_RETENTION] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
