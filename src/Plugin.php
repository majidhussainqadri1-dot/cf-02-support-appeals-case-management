<?php

declare(strict_types=1);

namespace Sabri\CF02;

use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Activation\WordPressActivationEvidence;
use Sabri\CF02\Infrastructure\WordPress\Runtime;
use Throwable;

final class Plugin
{
    private const OPTION_INSTALLED_VERSION = 'cf02_installed_version';
    private const OPTION_ACTIVATION_STATE = 'cf02_activation_state';

    public static function activate(): void
    {
        update_option(self::OPTION_INSTALLED_VERSION, CF02_VERSION, false);
        update_option(self::OPTION_ACTIVATION_STATE, [
            'status' => 'pending',
            'plan_version' => CF02_PLAN_VERSION,
            'runtime_version' => CF02_VERSION,
            'reason' => 'Founder-approved activation evidence, staffing, dependency contracts, staging and rollback proof are required.',
            'updated_at' => gmdate('c'),
        ], false);
    }

    public static function boot(): void
    {
        load_plugin_textdomain(
            'cf-02-support-appeals-case-management',
            false,
            dirname(plugin_basename(CF02_PLUGIN_FILE)) . '/languages'
        );

        $decision = (new ActivationGate(new WordPressActivationEvidence()))->evaluate();

        if (!$decision->isAllowed()) {
            self::registerDormantState($decision->reasons());
            return;
        }

        try {
            Runtime::boot();
        } catch (Throwable $error) {
            self::registerDormantState(['Runtime dependency failed closed: ' . sanitize_text_field($error->getMessage())]);
            do_action('cf02_runtime_failed_closed', $error);
            return;
        }
        update_option(self::OPTION_INSTALLED_VERSION, CF02_VERSION, false);
        update_option(self::OPTION_ACTIVATION_STATE, [
            'status' => 'ready',
            'plan_version' => CF02_PLAN_VERSION,
            'runtime_version' => CF02_VERSION,
            'evidence' => $decision->evidence(),
            'updated_at' => gmdate('c'),
        ], false);
        do_action('cf02_runtime_ready', $decision->evidence());
    }

    /** @param list<string> $reasons */
    private static function registerDormantState(array $reasons): void
    {
        update_option(self::OPTION_ACTIVATION_STATE, [
            'status' => 'dormant',
            'plan_version' => CF02_PLAN_VERSION,
            'runtime_version' => CF02_VERSION,
            'reasons' => $reasons,
            'updated_at' => gmdate('c'),
        ], false);

        add_action('admin_notices', static function () use ($reasons): void {
            if (!current_user_can('manage_options')) {
                return;
            }

            $message = __('CF-02 remains safely dormant until its activation gates pass.', 'cf-02-support-appeals-case-management');
            $details = implode(' ', array_map('sanitize_text_field', $reasons));

            printf(
                '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
                esc_html($message),
                esc_html($details)
            );
        });

        add_filter('site_status_tests', static function (array $tests) use ($reasons): array {
            $tests['direct']['cf02_activation_gates'] = [
                'label' => __('CF-02 activation gates', 'cf-02-support-appeals-case-management'),
                'test' => static function () use ($reasons): array {
                    return [
                        'label' => __('CF-02 is dormant by design', 'cf-02-support-appeals-case-management'),
                        'status' => 'recommended',
                        'badge' => [
                            'label' => __('Conditional module', 'cf-02-support-appeals-case-management'),
                            'color' => 'blue',
                        ],
                        'description' => '<p>' . esc_html(implode(' ', $reasons)) . '</p>',
                        'actions' => '',
                        'test' => 'cf02_activation_gates',
                    ];
                },
            ];

            return $tests;
        });
    }
}
