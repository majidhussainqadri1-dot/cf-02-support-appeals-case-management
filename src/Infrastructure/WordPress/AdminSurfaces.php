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
                'cf02_support_workspace',
                'cf02-support',
                [self::class, 'render'],
                'dashicons-sos',
                58
            );
            add_submenu_page('cf02-support', __('Support Quality', 'cf-02-support-appeals-case-management'), __('Quality', 'cf-02-support-appeals-case-management'), 'cf02_support_workspace', 'cf02-support-quality', [self::class, 'render']);
        });
    }

    public static function render(): void
    {
        if (!current_user_can('cf02_support_workspace')) {
            wp_die(esc_html__('You are not authorized to view this support workspace.', 'cf-02-support-appeals-case-management'), 403);
        }
        $state = get_option('cf02_activation_state', []);
        $status = is_array($state) ? sanitize_text_field((string) ($state['status'] ?? 'unknown')) : 'unknown';
        $section = sanitize_key((string) ($_GET['section'] ?? 'queues'));
        $allowed = ['queues','cases','appeals','sla','quality','configuration','reconciliation','retention'];
        if (!in_array($section, $allowed, true)) {
            $section = 'queues';
        }
        echo '<div class="wrap"><h1>' . esc_html__('Support Operations', 'cf-02-support-appeals-case-management') . '</h1>';
        echo '<div class="notice notice-info inline"><p><strong>' . esc_html__('Runtime state:', 'cf-02-support-appeals-case-management') . '</strong> ' . esc_html($status) . '</p></div>';
        echo '<p>' . esc_html__('Every detailed query is purpose-bound and object-authorized. This shell never grants identity, moderation, payment, clinical or security-incident authority.', 'cf-02-support-appeals-case-management') . '</p>';
        echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__('Support operations sections', 'cf-02-support-appeals-case-management') . '">';
        foreach ($allowed as $key) {
            $label = ucwords(str_replace('_', ' ', $key));
            $class = $section === $key ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($class) . '" href="' . esc_url(admin_url('admin.php?page=cf02-support&section=' . $key)) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav><section aria-live="polite"><h2>' . esc_html(ucwords(str_replace('_', ' ', $section))) . '</h2><p>'
            . esc_html__('Data is loaded through /wp-json/api/support/v1/staff/* using the current File 00 assertion, a bounded purpose, assignment relationship, field class and fresh record version.', 'cf-02-support-appeals-case-management')
            . '</p><div id="cf02-admin-workspace" data-section="' . esc_attr($section) . '" data-endpoint="' . esc_url(rest_url('api/support/v1')) . '" data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '"></div></section></div>';
    }
}
