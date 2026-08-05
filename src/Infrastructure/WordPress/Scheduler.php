<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

final class Scheduler
{
    public const HOOK_OUTBOX = 'cf02_process_outbox';
    public const HOOK_EVENTS = 'cf02_process_events';
    public const HOOK_SLA = 'cf02_process_sla';
    public const HOOK_RETENTION = 'cf02_process_retention';
    public const HOOK_RECONCILIATION = 'cf02_process_reconciliation';
    public const HOOK_KEY_ROTATION = 'cf02_process_key_rotation';

    public static function register(?RuntimeWorker $worker = null): void
    {
        add_filter('cron_schedules', static function (array $schedules): array {
            $schedules['cf02_five_minutes'] = [
                'interval' => 300,
                'display' => __('Every five minutes (CF-02)', 'cf-02-support-appeals-case-management'),
            ];
            return $schedules;
        });

        foreach ([
            self::HOOK_OUTBOX => 60,
            self::HOOK_EVENTS => 90,
            self::HOOK_RECONCILIATION => 120,
            self::HOOK_SLA => 150,
            self::HOOK_KEY_ROTATION => 180,
        ] as $hook => $offset) {
            if (!wp_next_scheduled($hook)) {
                wp_schedule_event(time() + $offset, 'cf02_five_minutes', $hook);
            }
        }
        if (!wp_next_scheduled(self::HOOK_RETENTION)) {
            wp_schedule_event(time() + 300, 'daily', self::HOOK_RETENTION);
        }

        if ($worker instanceof RuntimeWorker) {
            add_action(self::HOOK_OUTBOX, static function () use ($worker): void {
                $result = $worker->processOutbox(100);
                do_action('cf02_outbox_worker_completed', $result);
            });
            add_action(self::HOOK_EVENTS, static function () use ($worker): void {
                $result = $worker->processEvents(100);
                do_action('cf02_event_worker_completed', $result);
            });
            add_action(self::HOOK_RECONCILIATION, static function () use ($worker): void {
                $result = $worker->processCommands(100);
                do_action('cf02_reconciliation_worker_completed', $result);
            });
            add_action(self::HOOK_SLA, static function () use ($worker): void {
                $result = $worker->processSla(100);
                do_action('cf02_sla_worker_completed', $result);
            });
            add_action(self::HOOK_KEY_ROTATION, static function () use ($worker): void {
                $result = $worker->processKeyRotation(100);
                do_action('cf02_key_rotation_worker_completed', $result);
            });
            add_action(self::HOOK_RETENTION, static function () use ($worker): void {
                $result = $worker->processRetention(250);
                do_action('cf02_retention_worker_completed', $result);
            });
        }
    }

    /** @return array<string,mixed> */
    public static function inspection(): array
    {
        $hooks = [];
        foreach ([self::HOOK_OUTBOX, self::HOOK_EVENTS, self::HOOK_RECONCILIATION, self::HOOK_SLA, self::HOOK_KEY_ROTATION, self::HOOK_RETENTION] as $hook) {
            $next = wp_next_scheduled($hook);
            $hooks[$hook] = ['scheduled' => $next !== false, 'next_at' => $next === false ? null : gmdate(DATE_ATOM, (int) $next)];
        }
        return $hooks;
    }

    public static function repairRegistration(): void
    {
        self::register();
    }

    public static function unschedule(): void
    {
        foreach ([self::HOOK_OUTBOX, self::HOOK_EVENTS, self::HOOK_RECONCILIATION, self::HOOK_SLA, self::HOOK_KEY_ROTATION, self::HOOK_RETENTION] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }
}
