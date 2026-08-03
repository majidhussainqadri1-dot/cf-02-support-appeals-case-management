<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

final class FrontendSurfaces
{
    public static function register(): void
    {
        add_shortcode('cf02_support', [self::class, 'support']);
        add_shortcode('cf02_case_portal', [self::class, 'casePortal']);
        add_shortcode('cf02_appeal_portal', [self::class, 'appealPortal']);
        add_action('wp_enqueue_scripts', [self::class, 'assets']);
    }

    public static function assets(): void
    {
        wp_register_style('cf02-surfaces', false, [], CF02_VERSION);
        wp_enqueue_style('cf02-surfaces');
        wp_add_inline_style('cf02-surfaces', self::css());
    }

    public static function support(): string
    {
        if (!is_user_logged_in()) {
            return '<section class="cf02-card" aria-labelledby="cf02-login-title"><h2 id="cf02-login-title">' . esc_html__('Support', 'cf-02-support-appeals-case-management') . '</h2><p>' . esc_html__('Sign in to create, view or reply to a private support case.', 'cf-02-support-appeals-case-management') . '</p></section>';
        }
        $nonce = wp_create_nonce('wp_rest');
        return '<section class="cf02-card" aria-labelledby="cf02-support-title" data-cf02-endpoint="' . esc_url(rest_url('cf02/v1/cases')) . '" data-cf02-nonce="' . esc_attr($nonce) . '">
            <h2 id="cf02-support-title">' . esc_html__('Create a support case', 'cf-02-support-appeals-case-management') . '</h2>
            <div class="cf02-alert" role="note"><strong>' . esc_html__('Safety:', 'cf-02-support-appeals-case-management') . '</strong> ' . esc_html__('Do not send passwords, OTPs, private keys, full card data or emergency clinical requests. For immediate danger, use approved local emergency services.', 'cf-02-support-appeals-case-management') . '</div>
            <form class="cf02-form" method="post" novalidate>
              <label for="cf02-category">' . esc_html__('Category', 'cf-02-support-appeals-case-management') . '</label>
              <select id="cf02-category" name="category" required><option value="">' . esc_html__('Choose a category', 'cf-02-support-appeals-case-management') . '</option><option value="technical">' . esc_html__('Technical', 'cf-02-support-appeals-case-management') . '</option><option value="account_access">' . esc_html__('Account access', 'cf-02-support-appeals-case-management') . '</option><option value="privacy">' . esc_html__('Privacy', 'cf-02-support-appeals-case-management') . '</option><option value="moderation_appeal">' . esc_html__('Moderation or listing appeal', 'cf-02-support-appeals-case-management') . '</option><option value="other">' . esc_html__('Other', 'cf-02-support-appeals-case-management') . '</option></select>
              <label for="cf02-subject">' . esc_html__('Subject', 'cf-02-support-appeals-case-management') . '</label>
              <input id="cf02-subject" name="subject" maxlength="180" autocomplete="off" required aria-describedby="cf02-secret-help">
              <p id="cf02-secret-help" class="cf02-help">' . esc_html__('Describe the issue without secrets or unnecessary private information.', 'cf-02-support-appeals-case-management') . '</p>
              <label for="cf02-locale">' . esc_html__('Preferred language', 'cf-02-support-appeals-case-management') . '</label>
              <select id="cf02-locale" name="locale"><option value="ur-PK">اردو</option><option value="en-US">English</option></select>
              <button type="submit">' . esc_html__('Submit case', 'cf-02-support-appeals-case-management') . '</button>
              <div class="cf02-status" role="status" aria-live="polite"></div>
            </form>
          </section>' . self::script();
    }

    public static function casePortal(): string
    {
        return '<section class="cf02-card" aria-labelledby="cf02-cases-title"><h2 id="cf02-cases-title">' . esc_html__('My support cases', 'cf-02-support-appeals-case-management') . '</h2><p>' . esc_html__('Private case records are noindex and no-store. Only the requester or a verified representative may view them.', 'cf-02-support-appeals-case-management') . '</p><div class="cf02-status" role="status" aria-live="polite">' . esc_html__('Use the authenticated case API to load your cases.', 'cf-02-support-appeals-case-management') . '</div></section>';
    }

    public static function appealPortal(): string
    {
        return '<section class="cf02-card" aria-labelledby="cf02-appeal-title"><h2 id="cf02-appeal-title">' . esc_html__('Appeals', 'cf-02-support-appeals-case-management') . '</h2><p>' . esc_html__('Appeals are reviewed independently. The original decision remains immutable, and the native owner must confirm implementation of any changed outcome.', 'cf-02-support-appeals-case-management') . '</p><p>' . esc_html__('Using an appeal does not reduce ranking, entitlement or access to ordinary support.', 'cf-02-support-appeals-case-management') . '</p></section>';
    }

    private static function script(): string
    {
        return '<script>(function(){"use strict";const root=document.querySelector("[data-cf02-endpoint]");if(!root)return;const form=root.querySelector("form"),status=root.querySelector(".cf02-status");form.addEventListener("submit",async function(event){event.preventDefault();status.textContent="' . esc_js(__('Submitting…', 'cf-02-support-appeals-case-management')) . '";const data=new FormData(form),category=data.get("category"),payload={category:category,priority:"P3",severity:"normal",queue:category==="privacy"?"privacy_liaison":"technical",locale:data.get("locale"),subject:data.get("subject"),idempotency_key:"web_"+crypto.randomUUID().replaceAll("-","")};try{const response=await fetch(root.dataset.cf02Endpoint,{method:"POST",credentials:"same-origin",headers:{"Content-Type":"application/json","X-WP-Nonce":root.dataset.cf02Nonce},body:JSON.stringify(payload)});const body=await response.json();if(!response.ok)throw new Error(body.message||"Request failed");status.textContent="' . esc_js(__('Case accepted.', 'cf-02-support-appeals-case-management')) . '";form.reset();}catch(error){status.textContent=error.message||"' . esc_js(__('The case could not be submitted.', 'cf-02-support-appeals-case-management')) . '";}});})();</script>';
    }

    private static function css(): string
    {
        return '.cf02-card{max-width:52rem;margin:1rem auto;padding:1.25rem;border:1px solid #d9d9d9;border-radius:.75rem;background:#fff;color:#1f1f1f}.cf02-form{display:grid;gap:.75rem}.cf02-form input,.cf02-form select,.cf02-form button{font:inherit;min-height:44px;padding:.625rem;border:1px solid #666;border-radius:.4rem}.cf02-form button{cursor:pointer;font-weight:700}.cf02-form :focus-visible{outline:3px solid currentColor;outline-offset:2px}.cf02-alert{padding:.75rem;border-inline-start:4px solid #a34b00;background:#fff5e8}.cf02-help{margin:0;font-size:.925rem}.cf02-status{min-height:1.5rem}@media(prefers-reduced-motion:reduce){.cf02-card *{scroll-behavior:auto!important;transition:none!important}}@media(max-width:480px){.cf02-card{margin:.5rem;padding:1rem}}';
    }
}
