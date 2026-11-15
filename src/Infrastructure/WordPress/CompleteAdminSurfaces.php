<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Authorization\WordPressPrincipalContextFactory;
use Throwable;

/**
 * File 00 capability-bound support administration.
 * External assets keep the operational surface compatible with strict CSP.
 */
final class CompleteAdminSurfaces
{
    private const DOMAIN = 'cf-02-support-appeals-case-management';
    private const PAGE_HOOK = 'toplevel_page_cf02-support-complete';

    public static function register(): void
    {
        add_action('admin_menu', static function (): void {
            $context = self::context();
            if (!$context || !self::allowed($context)) {
                return;
            }
            add_menu_page(
                __('Support Operations', self::DOMAIN),
                __('Support', self::DOMAIN),
                'read',
                'cf02-support-complete',
                [self::class, 'render'],
                'dashicons-sos',
                58
            );
        });
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(string $hook): void
    {
        if ($hook !== self::PAGE_HOOK) {
            return;
        }
        $context = self::context();
        if (!$context || !self::allowed($context)) {
            return;
        }
        wp_enqueue_style(
            'cf02-admin-surfaces',
            plugins_url('assets/css/cf02-admin.css', CF02_PLUGIN_FILE),
            [],
            CF02_VERSION
        );
        wp_enqueue_script(
            'cf02-admin-surfaces',
            plugins_url('assets/js/cf02-admin.js', CF02_PLUGIN_FILE),
            [],
            CF02_VERSION,
            true
        );
    }

    public static function render(): void
    {
        $context = self::context();
        if (!$context || !self::allowed($context)) {
            wp_die(esc_html__('Resource not found.', self::DOMAIN), 404);
        }
        $translations = [
            'loading' => __('Loading…', self::DOMAIN),
            'failed' => __('Request failed.', self::DOMAIN),
            'ready' => __('Ready', self::DOMAIN),
            'repairReference' => __('Approved repair reference', self::DOMAIN),
            'runRepair' => __('Run governed repair', self::DOMAIN),
            'confirmRepair' => __('Run the approved non-destructive repair now?', self::DOMAIN),
            'repairResult' => __('Repair command returned. Verify server evidence.', self::DOMAIN),
        ];
        $views = [
            'queue' => __('Queue', self::DOMAIN),
            'search' => __('Cases', self::DOMAIN),
            'appeals' => __('Appeals', self::DOMAIN),
            'sla' => __('SLA', self::DOMAIN),
            'quality' => __('Quality', self::DOMAIN),
            'retention' => __('Retention', self::DOMAIN),
            'repair' => __('Repair', self::DOMAIN),
        ];
        echo '<div class="wrap cf02-admin" data-cf02-admin data-endpoint="'
            . esc_url(rest_url('api/support/v1'))
            . '" data-nonce="' . esc_attr(wp_create_nonce('wp_rest'))
            . '" data-i18n="' . esc_attr((string) wp_json_encode($translations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT))
            . '"><h1>' . esc_html__('Support Operations', self::DOMAIN)
            . '</h1><p>' . esc_html__('This workspace displays minimum necessary case projections. Every operation is re-authorized through File 00; WordPress administrator status alone is insufficient.', self::DOMAIN)
            . '</p><nav class="nav-tab-wrapper" aria-label="' . esc_attr__('Support sections', self::DOMAIN) . '">';
        foreach ($views as $key => $label) {
            echo '<button type="button" class="nav-tab" data-viewkey="' . esc_attr($key)
                . '" aria-pressed="' . ($key === 'queue' ? 'true' : 'false') . '">'
                . esc_html($label) . '</button>';
        }
        echo '</nav><section data-output aria-live="polite" aria-atomic="false"></section>'
            . '<div data-status role="status" aria-live="polite"></div></div>';
    }

    private static function context(): ?PrincipalContext
    {
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $context = (new WordPressPrincipalContextFactory())->current($now);
            return $context->validAt($now) ? $context : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function allowed(PrincipalContext $context): bool
    {
        return $context->hasAnyCapability(
            'queue.assigned.read',
            'queue.specialist.read',
            'queue.manage',
            'appeal.queue.read',
            'appeal.review',
            'metrics.read',
            'quality.manage',
            'retention.review',
            'release.evidence.read',
            'repair.inspect',
            'repair.execute'
        );
    }
}
