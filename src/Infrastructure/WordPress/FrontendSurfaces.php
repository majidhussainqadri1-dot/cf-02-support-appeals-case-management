<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Contracts\SupportContractCatalog;

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
        $help = '<section class="cf02-card" aria-labelledby="cf02-help-title"><h1 id="cf02-help-title">' . esc_html__('Support and Appeals', 'cf-02-support-appeals-case-management') . '</h1>'
            . '<p>' . esc_html__('Browse help publicly. Sign in only when you need to create, view, reply to, reopen or appeal a private case.', 'cf-02-support-appeals-case-management') . '</p>'
            . self::safetyNotice()
            . '<details><summary>' . esc_html__('What support does not do', 'cf-02-support-appeals-case-management') . '</summary><p>'
            . esc_html__('Support coordinates cases. Identity, moderation, payment, privacy-incident and clinical decisions stay with their canonical owners. Never send passwords, OTPs, private keys or full payment-card data.', 'cf-02-support-appeals-case-management')
            . '</p></details></section>';

        if (!is_user_logged_in()) {
            return $help . '<section class="cf02-card"><h2>' . esc_html__('Private case actions require sign-in', 'cf-02-support-appeals-case-management') . '</h2><p>'
                . esc_html__('After signing in, the same page provides guided case intake and your private case list.', 'cf-02-support-appeals-case-management') . '</p></section>';
        }

        $nonce = wp_create_nonce('wp_rest');
        $options = '<option value="">' . esc_html__('Choose a category', 'cf-02-support-appeals-case-management') . '</option>';
        foreach (SupportContractCatalog::categories() as $category) {
            $options .= '<option value="' . esc_attr($category) . '">' . esc_html(self::categoryLabel($category)) . '</option>';
        }
        return $help . '<section class="cf02-card" aria-labelledby="cf02-create-title" data-cf02-app data-endpoint="' . esc_url(rest_url('api/support/v1')) . '" data-nonce="' . esc_attr($nonce) . '">
            <h2 id="cf02-create-title">' . esc_html__('Create a support case', 'cf-02-support-appeals-case-management') . '</h2>
            <form class="cf02-form" data-cf02-create novalidate>
              <label for="cf02-category">' . esc_html__('Category', 'cf-02-support-appeals-case-management') . '</label>
              <select id="cf02-category" name="category" required>' . $options . '</select>
              <label for="cf02-subject">' . esc_html__('Short subject', 'cf-02-support-appeals-case-management') . '</label>
              <input id="cf02-subject" name="subject" maxlength="191" autocomplete="off" required aria-describedby="cf02-safe-help">
              <label for="cf02-description">' . esc_html__('Description', 'cf-02-support-appeals-case-management') . '</label>
              <textarea id="cf02-description" name="description" maxlength="20000" rows="6" aria-describedby="cf02-safe-help"></textarea>
              <p id="cf02-safe-help" class="cf02-help">' . esc_html__('Use the minimum necessary information. Do not include secrets or unrelated medical records.', 'cf-02-support-appeals-case-management') . '</p>
              <div class="cf02-grid"><label>' . esc_html__('Impact', 'cf-02-support-appeals-case-management') . '<select name="impact"><option value="single_action">' . esc_html__('One action', 'cf-02-support-appeals-case-management') . '</option><option value="account_blocked">' . esc_html__('Account or essential task blocked', 'cf-02-support-appeals-case-management') . '</option><option value="many_users">' . esc_html__('Many users affected', 'cf-02-support-appeals-case-management') . '</option></select></label><label>' . esc_html__('Urgency', 'cf-02-support-appeals-case-management') . '<select name="urgency"><option value="normal">' . esc_html__('Normal', 'cf-02-support-appeals-case-management') . '</option><option value="time_sensitive">' . esc_html__('Time-sensitive', 'cf-02-support-appeals-case-management') . '</option></select></label></div>
              <label for="cf02-accessibility">' . esc_html__('Accessibility or language support needed', 'cf-02-support-appeals-case-management') . '</label>
              <input id="cf02-accessibility" name="accessibility" maxlength="191">
              <label class="cf02-check"><input type="checkbox" name="diagnostics_consented" value="1"> ' . esc_html__('I consent to the minimum contextual diagnostics needed for this case.', 'cf-02-support-appeals-case-management') . '</label>
              <button type="submit">' . esc_html__('Submit case', 'cf-02-support-appeals-case-management') . '</button>
              <div class="cf02-status" role="status" aria-live="polite"></div>
            </form>
          </section>' . self::casePortal() . self::script();
    }

    public static function casePortal(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }
        return '<section class="cf02-card" aria-labelledby="cf02-cases-title"><h2 id="cf02-cases-title">' . esc_html__('My support cases', 'cf-02-support-appeals-case-management') . '</h2><p>'
            . esc_html__('Private records use no-store and noindex rules. Verified representatives see only the authority scope supplied by File 00.', 'cf-02-support-appeals-case-management')
            . '</p><button type="button" data-cf02-load>' . esc_html__('Load my cases', 'cf-02-support-appeals-case-management') . '</button><div data-cf02-list class="cf02-list" aria-live="polite"></div></section>';
    }

    public static function appealPortal(): string
    {
        return '<section class="cf02-card" aria-labelledby="cf02-appeal-title"><h2 id="cf02-appeal-title">' . esc_html__('Appeals', 'cf-02-support-appeals-case-management') . '</h2><p>'
            . esc_html__('An appeal keeps the original decision immutable, checks standing and timeliness, requires an independent reviewer, gives reasons and stays open until the native owner confirms implementation.', 'cf-02-support-appeals-case-management')
            . '</p><p>' . esc_html__('Appeal use must not reduce ranking, entitlement or ordinary support access.', 'cf-02-support-appeals-case-management') . '</p></section>';
    }

    private static function safetyNotice(): string
    {
        return '<div class="cf02-alert" role="note"><strong>' . esc_html__('Immediate danger:', 'cf-02-support-appeals-case-management') . '</strong> '
            . esc_html__('This is not an emergency or clinical queue. For immediate danger or an acute medical crisis, use approved local emergency services. Support does not diagnose or prescribe.', 'cf-02-support-appeals-case-management') . '</div>';
    }

    private static function categoryLabel(string $key): string
    {
        return match ($key) {
            'account_access' => __('Account access', 'cf-02-support-appeals-case-management'),
            'verification' => __('Verification', 'cf-02-support-appeals-case-management'),
            'learning_billing' => __('Learning or membership billing', 'cf-02-support-appeals-case-management'),
            'publishing' => __('Publishing', 'cf-02-support-appeals-case-management'),
            'clinic_appointment' => __('Clinic or appointment', 'cf-02-support-appeals-case-management'),
            'messages_calls' => __('Messages or calls', 'cf-02-support-appeals-case-management'),
            'media_pdf' => __('Media or PDF', 'cf-02-support-appeals-case-management'),
            'marketplace' => __('Marketplace', 'cf-02-support-appeals-case-management'),
            'privacy_data_rights' => __('Privacy or data rights', 'cf-02-support-appeals-case-management'),
            'safety_abuse' => __('Safety or abuse', 'cf-02-support-appeals-case-management'),
            'accessibility' => __('Accessibility', 'cf-02-support-appeals-case-management'),
            default => __('Technical', 'cf-02-support-appeals-case-management'),
        };
    }

    private static function script(): string
    {
        return '<script>(function(){"use strict";const root=document.querySelector("[data-cf02-app]");if(!root)return;const endpoint=root.dataset.endpoint.replace(/\/$/,""),nonce=root.dataset.nonce,headers={"Content-Type":"application/json","X-WP-Nonce":nonce};const status=root.querySelector(".cf02-status"),form=root.querySelector("[data-cf02-create]");async function api(path,options={}){const response=await fetch(endpoint+path,{credentials:"same-origin",headers:{...headers,...(options.headers||{})},...options});const body=await response.json();if(!response.ok)throw new Error(body.message||"Request failed");return body;}form.addEventListener("submit",async event=>{event.preventDefault();status.textContent="' . esc_js(__('Submitting…', 'cf-02-support-appeals-case-management')) . '";const data=new FormData(form),payload=Object.fromEntries(data.entries());payload.diagnostics_consented=data.has("diagnostics_consented");try{const result=await api("/cases",{method:"POST",headers:{"Idempotency-Key":"web:"+crypto.randomUUID()},body:JSON.stringify(payload)});status.textContent="' . esc_js(__('Case accepted:', 'cf-02-support-appeals-case-management')) . ' "+result.case.case_uuid;form.reset();}catch(error){status.textContent=error.message;}});const button=document.querySelector("[data-cf02-load]"),list=document.querySelector("[data-cf02-list]");if(button)button.addEventListener("click",async()=>{list.textContent="' . esc_js(__('Loading…', 'cf-02-support-appeals-case-management')) . '";try{const result=await api("/cases");list.replaceChildren(...result.items.map(item=>{const article=document.createElement("article");article.className="cf02-case";const h=document.createElement("h3");h.textContent=item.safe_subject;const p=document.createElement("p");p.textContent=item.case_uuid+" — "+item.state+" — "+item.priority;article.append(h,p);return article;}));if(!result.items.length)list.textContent="' . esc_js(__('No cases found.', 'cf-02-support-appeals-case-management')) . '";}catch(error){list.textContent=error.message;}});})();</script>';
    }

    private static function css(): string
    {
        return '.cf02-card{max-width:58rem;margin:1rem auto;padding:1.25rem;border:1px solid #d7d7d7;border-radius:.85rem;background:#fff;color:#1f1f1f}.cf02-form{display:grid;gap:.75rem}.cf02-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.cf02-form input,.cf02-form select,.cf02-form textarea,.cf02-form button,.cf02-card>button{font:inherit;min-height:44px;padding:.625rem;border:1px solid #555;border-radius:.45rem}.cf02-form textarea{resize:vertical}.cf02-form button,.cf02-card>button{cursor:pointer;font-weight:700}.cf02-form :focus-visible,.cf02-card :focus-visible{outline:3px solid currentColor;outline-offset:3px}.cf02-alert{padding:.8rem;border-inline-start:4px solid #a34b00;background:#fff5e8}.cf02-help{margin:0;font-size:.925rem}.cf02-check{display:flex;gap:.6rem;align-items:flex-start}.cf02-check input{min-height:auto;margin-top:.25rem}.cf02-status{min-height:1.5rem}.cf02-list{display:grid;gap:.75rem;margin-top:1rem}.cf02-case{padding:.8rem;border:1px solid #ddd;border-radius:.5rem}.cf02-case h3{margin-top:0}@media(max-width:600px){.cf02-grid{grid-template-columns:1fr}.cf02-card{margin:.5rem;padding:1rem}}@media(prefers-reduced-motion:reduce){.cf02-card *{scroll-behavior:auto!important;transition:none!important;animation:none!important}}';
    }
}
