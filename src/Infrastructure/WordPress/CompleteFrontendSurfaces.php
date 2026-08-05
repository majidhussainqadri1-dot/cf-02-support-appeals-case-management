<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Configuration\SupportTaxonomy;
use Sabri\CF02\Contracts\SupportContractCatalog;

/** Accessible requester/representative support, case and appeal journeys. */
final class CompleteFrontendSurfaces
{
    private const TEXT_DOMAIN = 'cf-02-support-appeals-case-management';

    public static function register(): void
    {
        add_shortcode('cf02_support', [self::class, 'support']);
        add_shortcode('cf02_case_portal', [self::class, 'portal']);
        add_shortcode('cf02_appeal_portal', [self::class, 'portal']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(): void
    {
        if (!self::shouldEnqueueAssets()) {
            return;
        }
        wp_enqueue_style(
            'cf02-complete-surfaces',
            plugins_url('assets/css/cf02-frontend.css', CF02_PLUGIN_FILE),
            [],
            CF02_VERSION
        );
        wp_enqueue_script(
            'cf02-complete-surfaces',
            plugins_url('assets/js/cf02-frontend.js', CF02_PLUGIN_FILE),
            [],
            CF02_VERSION,
            true
        );
    }

    private static function shouldEnqueueAssets(): bool
    {
        $path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        if ($path === '/support' || str_starts_with($path, '/support/')) {
            return true;
        }
        $post = $GLOBALS['post'] ?? null;
        if (!is_object($post) || !isset($post->post_content) || !is_string($post->post_content)) {
            return false;
        }
        foreach (['cf02_support', 'cf02_case_portal', 'cf02_appeal_portal'] as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        return false;
    }

    public static function support(): string
    {
        $public = '<section class="cf02-public cf02-card">'
            . '<h1>' . esc_html__('Support and Appeals', self::TEXT_DOMAIN) . '</h1>'
            . '<p>' . esc_html__('Public help is available without exposing private cases. Sign in for case or appeal actions.', self::TEXT_DOMAIN) . '</p>'
            . self::safety()
            . '<details><summary>' . esc_html__('Privacy boundary', self::TEXT_DOMAIN) . '</summary>'
            . '<p>' . esc_html__('Never submit passwords, OTPs, private keys, full payment-card data or unrelated clinical records. Native identity, payment, moderation, privacy-rights, security-incident and clinical decisions remain with their canonical owners.', self::TEXT_DOMAIN) . '</p>'
            . '<p>' . esc_html__('Donations, sponsorships and payments never change support priority, SLA, appeal eligibility or outcome.', self::TEXT_DOMAIN) . '</p>'
            . '</details></section>';

        if (!is_user_logged_in()) {
            return $public . '<section class="cf02-public cf02-card"><h2>'
                . esc_html__('Authentication required for private actions', self::TEXT_DOMAIN)
                . '</h2></section>';
        }

        return $public . self::app();
    }

    public static function portal(): string
    {
        return is_user_logged_in()
            ? self::app()
            : '<section class="cf02-public cf02-card">'
                . esc_html__('A valid File 00 session is required.', self::TEXT_DOMAIN)
                . '</section>';
    }

    private static function app(): string
    {
        static $rendered = false;
        if ($rendered) {
            return '';
        }
        $rendered = true;

        [$initialCase, $initialAppeal] = self::routeContext();
        $direction = is_rtl() ? 'rtl' : 'ltr';
        $strings = esc_attr((string) wp_json_encode(self::strings(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));

        return '<div class="cf02-app" dir="' . esc_attr($direction) . '" data-cf02-complete'
            . ' data-endpoint="' . esc_url(rest_url('api/support/v1')) . '"'
            . ' data-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '"'
            . ' data-initial-case="' . esc_attr($initialCase) . '"'
            . ' data-initial-appeal="' . esc_attr($initialAppeal) . '"'
            . ' data-i18n="' . $strings . '" aria-busy="false">'
            . '<div class="cf02-tabs" role="tablist" aria-label="' . esc_attr__('Support actions', self::TEXT_DOMAIN) . '">'
            . self::tab('new', 'cf02-panel-new', __('New case', self::TEXT_DOMAIN), 'plus', true)
            . self::tab('cases', 'cf02-panel-cases', __('My cases', self::TEXT_DOMAIN), 'cases', false)
            . self::tab('appeals', 'cf02-panel-appeals', __('Appeals', self::TEXT_DOMAIN), 'appeal', false)
            . '</div>'
            . self::newCasePanel()
            . self::casesPanel()
            . self::appealsPanel()
            . '<div class="cf02-status" role="status" aria-live="polite" aria-atomic="true"></div>'
            . '</div>';
    }

    private static function newCasePanel(): string
    {
        return '<section id="cf02-panel-new" class="cf02-card" role="tabpanel" aria-labelledby="cf02-tab-new" data-panel="new">'
            . '<h2 data-panel-heading tabindex="-1">' . esc_html__('Create support case', self::TEXT_DOMAIN) . '</h2>'
            . '<form data-form="case" class="cf02-form">'
            . '<label>' . esc_html__('Category', self::TEXT_DOMAIN) . '<select name="category" required>' . self::categoryOptions() . '</select></label>'
            . '<label>' . esc_html__('Issue type or subcategory', self::TEXT_DOMAIN) . '<input name="subcategory" maxlength="80" dir="auto"></label>'
            . '<label>' . esc_html__('Subject', self::TEXT_DOMAIN) . '<input name="subject" maxlength="191" required dir="auto"></label>'
            . '<label>' . esc_html__('Description', self::TEXT_DOMAIN) . '<textarea name="description" rows="6" maxlength="20000" dir="auto"></textarea></label>'
            . '<div class="cf02-grid">'
            . '<label>' . esc_html__('Impact', self::TEXT_DOMAIN) . '<select name="impact">'
            . '<option value="single_action">' . esc_html__('One action', self::TEXT_DOMAIN) . '</option>'
            . '<option value="account_blocked">' . esc_html__('Essential task blocked', self::TEXT_DOMAIN) . '</option>'
            . '<option value="many_users">' . esc_html__('Many users', self::TEXT_DOMAIN) . '</option>'
            . '</select></label>'
            . '<label>' . esc_html__('Urgency', self::TEXT_DOMAIN) . '<select name="urgency">'
            . '<option value="normal">' . esc_html__('Normal', self::TEXT_DOMAIN) . '</option>'
            . '<option value="time_sensitive">' . esc_html__('Time-sensitive', self::TEXT_DOMAIN) . '</option>'
            . '</select></label></div>'
            . '<label>' . esc_html__('Accessibility or language support', self::TEXT_DOMAIN) . '<input name="accessibility" maxlength="191" dir="auto"></label>'
            . '<fieldset><legend>' . esc_html__('Optional affected platform object', self::TEXT_DOMAIN) . '</legend><div class="cf02-grid">'
            . '<label>' . esc_html__('Owner domain', self::TEXT_DOMAIN) . '<select name="object_owner">' . self::ownerOptions() . '</select></label>'
            . '<label>' . esc_html__('Object type', self::TEXT_DOMAIN) . '<input name="object_type" maxlength="80" dir="ltr"></label>'
            . '<label>' . esc_html__('Object reference', self::TEXT_DOMAIN) . '<input name="object_ref" maxlength="191" dir="ltr"></label>'
            . '<label>' . esc_html__('Object version', self::TEXT_DOMAIN) . '<input name="object_version" maxlength="80" dir="ltr"></label>'
            . '</div></fieldset>'
            . '<label class="cf02-check"><input type="checkbox" name="diagnostics_consented"> '
            . esc_html__('I consent to minimum contextual diagnostics.', self::TEXT_DOMAIN) . '</label>'
            . '<button type="submit">' . self::icon('plus') . esc_html__('Submit case', self::TEXT_DOMAIN) . '</button>'
            . '</form></section>';
    }

    private static function casesPanel(): string
    {
        return '<section id="cf02-panel-cases" class="cf02-card" role="tabpanel" aria-labelledby="cf02-tab-cases" data-panel="cases" hidden>'
            . '<h2 data-panel-heading tabindex="-1">' . esc_html__('My cases', self::TEXT_DOMAIN) . '</h2>'
            . '<button type="button" data-action="cases">' . self::icon('refresh') . esc_html__('Refresh', self::TEXT_DOMAIN) . '</button>'
            . '<div data-view="cases"></div><div data-view="case"></div></section>';
    }

    private static function appealsPanel(): string
    {
        return '<section id="cf02-panel-appeals" class="cf02-card" role="tabpanel" aria-labelledby="cf02-tab-appeals" data-panel="appeals" hidden>'
            . '<h2 data-panel-heading tabindex="-1">' . esc_html__('Appeals', self::TEXT_DOMAIN) . '</h2>'
            . '<p>' . esc_html__('The original decision remains immutable; independent review and implementation confirmation are required.', self::TEXT_DOMAIN) . '</p>'
            . '<p>' . esc_html__('Using an appeal cannot reduce ranking, access, support quality or future eligibility.', self::TEXT_DOMAIN) . '</p>'
            . '<form data-form="appeal-load" class="cf02-form">'
            . '<label>' . esc_html__('Appeal ID', self::TEXT_DOMAIN) . '<input name="id" placeholder="CF02-APL-…" required dir="ltr"></label>'
            . '<button type="submit">' . self::icon('appeal') . esc_html__('Load appeal', self::TEXT_DOMAIN) . '</button>'
            . '</form><div data-view="appeal"></div></section>';
    }

    private static function categoryOptions(): string
    {
        $options = '<option value="">' . esc_html__('Choose category', self::TEXT_DOMAIN) . '</option>';
        foreach (SupportTaxonomy::defaults() as $category) {
            $options .= '<option value="' . esc_attr($category->key()) . '">'
                . esc_html(translate($category->label(), self::TEXT_DOMAIN))
                . '</option>';
        }
        return $options;
    }

    private static function ownerOptions(): string
    {
        $labels = [
            'identity' => __('Identity and account', self::TEXT_DOMAIN),
            'membership' => __('Membership', self::TEXT_DOMAIN),
            'messages_reports' => __('Messages and reports', self::TEXT_DOMAIN),
            'notifications' => __('Notifications', self::TEXT_DOMAIN),
            'shell_routes' => __('Application shell and routes', self::TEXT_DOMAIN),
            'content_moderation' => __('Content or listing moderation', self::TEXT_DOMAIN),
            'security_assurance' => __('Security and privacy assurance', self::TEXT_DOMAIN),
            'visual_components' => __('Visual components', self::TEXT_DOMAIN),
            'search_ranking' => __('Search and ranking', self::TEXT_DOMAIN),
            'payments' => __('Financial action', self::TEXT_DOMAIN),
            'clinical' => __('Clinical safety owner', self::TEXT_DOMAIN),
        ];
        $options = '<option value="">' . esc_html__('No linked platform object', self::TEXT_DOMAIN) . '</option>';
        foreach (SupportContractCatalog::nativeOwners() as $key => $owner) {
            $label = $labels[$key] ?? $key;
            $options .= '<option value="' . esc_attr($key) . '">'
                . esc_html(sprintf('%s — %s', $label, $owner))
                . '</option>';
        }
        return $options;
    }

    private static function tab(string $key, string $panelId, string $label, string $icon, bool $selected): string
    {
        return '<button id="cf02-tab-' . esc_attr($key) . '" type="button" role="tab" data-tab="' . esc_attr($key) . '"'
            . ' aria-controls="' . esc_attr($panelId) . '" aria-selected="' . ($selected ? 'true' : 'false') . '"'
            . ' tabindex="' . ($selected ? '0' : '-1') . '">'
            . self::icon($icon) . esc_html($label) . '</button>';
    }

    private static function icon(string $name): string
    {
        $paths = [
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'cases' => '<path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
            'appeal' => '<path d="M4 12a8 8 0 1 0 2.3-5.7"/><path d="M4 4v6h6"/>',
            'refresh' => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
        ];
        $path = $paths[$name] ?? $paths['cases'];
        return '<span class="cf02-icon" aria-hidden="true"><svg viewBox="0 0 24 24">' . $path . '</svg></span>';
    }

    /** @return array<string,string> */
    private static function strings(): array
    {
        return [
            'requestFailed' => __('The request failed.', self::TEXT_DOMAIN),
            'loading' => __('Loading…', self::TEXT_DOMAIN),
            'noCases' => __('No cases found.', self::TEXT_DOMAIN),
            'open' => __('Open', self::TEXT_DOMAIN),
            'loadMore' => __('Load more', self::TEXT_DOMAIN),
            'supportTeam' => __('Support Team', self::TEXT_DOMAIN),
            'requester' => __('Requester', self::TEXT_DOMAIN),
            'unavailable' => __('[Unavailable]', self::TEXT_DOMAIN),
            'reply' => __('Reply', self::TEXT_DOMAIN),
            'sendReply' => __('Send reply', self::TEXT_DOMAIN),
            'replySent' => __('Reply sent.', self::TEXT_DOMAIN),
            'secureAttachment' => __('Secure attachment', self::TEXT_DOMAIN),
            'file' => __('File', self::TEXT_DOMAIN),
            'purpose' => __('Purpose', self::TEXT_DOMAIN),
            'privacy' => __('Privacy', self::TEXT_DOMAIN),
            'uploadConsent' => __('I consent to this case-specific upload.', self::TEXT_DOMAIN),
            'uploadQuarantine' => __('Upload to quarantine', self::TEXT_DOMAIN),
            'quarantineFailed' => __('Quarantine upload failed.', self::TEXT_DOMAIN),
            'attachmentQuarantined' => __('Attachment quarantined; scan pending.', self::TEXT_DOMAIN),
            'withdraw' => __('Withdraw', self::TEXT_DOMAIN),
            'reopen' => __('Reopen', self::TEXT_DOMAIN),
            'optionalFeedback' => __('Optional feedback', self::TEXT_DOMAIN),
            'rating' => __('Rating', self::TEXT_DOMAIN),
            'comment' => __('Comment', self::TEXT_DOMAIN),
            'optOut' => __('Opt out', self::TEXT_DOMAIN),
            'submit' => __('Submit', self::TEXT_DOMAIN),
            'feedbackRecorded' => __('Feedback recorded.', self::TEXT_DOMAIN),
            'submitAppeal' => __('Submit appeal', self::TEXT_DOMAIN),
            'decisionReference' => __('Decision reference', self::TEXT_DOMAIN),
            'policyVersion' => __('Policy version', self::TEXT_DOMAIN),
            'grounds' => __('Grounds', self::TEXT_DOMAIN),
            'evidenceReferences' => __('Evidence references', self::TEXT_DOMAIN),
            'appealSubmitted' => __('Appeal submitted:', self::TEXT_DOMAIN),
            'caseAccepted' => __('Case accepted:', self::TEXT_DOMAIN),
            'statusAvailable' => __('Status available.', self::TEXT_DOMAIN),
            'offline' => __('You are offline. Reconnect and retry.', self::TEXT_DOMAIN),
            'online' => __('Connection restored.', self::TEXT_DOMAIN),
            'unexpectedError' => __('An unexpected error occurred.', self::TEXT_DOMAIN),
            'secureBrowserRequired' => __('A secure modern browser is required for this action.', self::TEXT_DOMAIN),
            'accepted' => __('accepted', self::TEXT_DOMAIN),
        ];
    }

    /** @return array{string,string} */
    private static function routeContext(): array
    {
        $path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        $case = preg_match('#/support/cases/(CF02-[0-9A-F-]{36})(?:/|$)#', $path, $match) === 1 ? $match[1] : '';
        $appeal = preg_match('#/support/appeals/(CF02-APL-[A-F0-9]{20})(?:/|$)#', $path, $match) === 1 ? $match[1] : '';
        return [$case, $appeal];
    }

    private static function safety(): string
    {
        return '<div class="cf02-alert" role="note"><strong>'
            . esc_html__('Immediate danger:', self::TEXT_DOMAIN)
            . '</strong> '
            . esc_html__('This is not an emergency or clinical queue. Use approved local emergency services. Support does not diagnose or prescribe.', self::TEXT_DOMAIN)
            . '</div>';
    }
}
