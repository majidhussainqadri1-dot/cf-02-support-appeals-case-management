<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

final class AdminSurfaces
{
    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            add_menu_page(
                __('Support Operations', 'cf-02-support-appeals-case-management'),
                __('Support', 'cf-02-support-appeals-case-management'),
                'manage_options',
                'cf02-support',
                [self::class, 'render'],
                'dashicons-sos',
                58
            );
        });
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not authorized to view this support workspace.', 'cf-02-support-appeals-case-management'), 403);
        }
        $state = get_option('cf02_activation_state', []);
        $status = is_array($state) ? sanitize_text_field((string) ($state['status'] ?? 'unknown')) : 'unknown';
        echo '<div class="wrap"><h1>' . esc_html__('Support Operations', 'cf-02-support-appeals-case-management') . '</h1>';
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Runtime state:', 'cf-02-support-appeals-case-management') . '</strong> ' . esc_html($status) . '</p></div>';
        echo '<p>' . esc_html__('This workbench is a bounded operational shell. Native identity, moderation, payment, privacy-incident and clinical decisions remain with their canonical owners.', 'cf-02-support-appeals-case-management') . '</p>';
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__('Support operations sections', 'cf-02-support-appeals-case-management') . '">';
        foreach ([
            'queues' => __('Queues', 'cf-02-support-appeals-case-management'),
            'cases' => __('Cases', 'cf-02-support-appeals-case-management'),
            'appeals' => __('Appeals', 'cf-02-support-appeals-case-management'),
            'sla' => __('SLA and Escalation', 'cf-02-support-appeals-case-management'),
            'quality' => __('Quality', 'cf-02-support-appeals-case-management'),
            'configuration' => __('Configuration', 'cf-02-support-appeals-case-management'),
            'reconciliation' => __('Reconciliation', 'cf-02-support-appeals-case-management'),
        ] as $key => $label) {
            echo '<a class="nav-tab" href="' . esc_url(admin_url('admin.php?page=cf02-support&section=' . $key)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav><section aria-live="polite"><h2>' . esc_html__('Controlled workspace', 'cf-02-support-appeals-case-management') . '</h2><p>' . esc_html__('Detailed records are loaded only through purpose-bound, object-authorized APIs. This shell does not expose case bodies, credentials or unrestricted cross-user search.', 'cf-02-support-appeals-case-management') . '</p></section></div>';
    }
}
