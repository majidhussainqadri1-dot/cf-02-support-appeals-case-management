<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Contracts\SupportContractCatalog;

/** Plan-complete requester/representative support and appeal journeys. */
final class CompleteFrontendSurfaces
{
    public static function register(): void
    {
        add_shortcode('cf02_support',[self::class,'support']);
        add_shortcode('cf02_case_portal',[self::class,'portal']);
        add_shortcode('cf02_appeal_portal',[self::class,'portal']);
        add_action('wp_enqueue_scripts',[self::class,'assets']);
    }

    public static function assets(): void
    {
        wp_register_style('cf02-complete-surfaces',false,[],CF02_VERSION);wp_enqueue_style('cf02-complete-surfaces');wp_add_inline_style('cf02-complete-surfaces',self::css());
    }

    public static function support(): string
    {
        $public='<section class="cf02-card"><h1>'.esc_html__('Support and Appeals','cf-02-support-appeals-case-management').'</h1><p>'.esc_html__('Public help is available without exposing private cases. Sign in for case or appeal actions.','cf-02-support-appeals-case-management').'</p>'.self::safety().'<details><summary>'.esc_html__('Privacy boundary','cf-02-support-appeals-case-management').'</summary><p>'.esc_html__('Never submit passwords, OTPs, private keys, full payment-card data or unrelated clinical records. Native identity, payment, moderation, privacy-rights, security-incident and clinical decisions remain with their canonical owners.','cf-02-support-appeals-case-management').'</p></details></section>';
        return $public.(is_user_logged_in()?self::app():'<section class="cf02-card"><h2>'.esc_html__('Authentication required for private actions','cf-02-support-appeals-case-management').'</h2></section>');
    }

    public static function portal(): string { return is_user_logged_in()?self::app():'<section class="cf02-card">'.esc_html__('A valid File 00 session is required.','cf-02-support-appeals-case-management').'</section>'; }

    private static function app(): string
    {
        static $done=false;if($done)return '';$done=true;$options='<option value="">'.esc_html__('Choose category','cf-02-support-appeals-case-management').'</option>';foreach(SupportContractCatalog::categories() as $c){$options.='<option value="'.esc_attr($c).'">'.esc_html(ucwords(str_replace('_',' ',$c))).'</option>';}
        return '<div class="cf02-app" data-cf02-complete data-endpoint="'.esc_url(rest_url('api/support/v1')).'" data-nonce="'.esc_attr(wp_create_nonce('wp_rest')).'">
        <nav class="cf02-tabs" aria-label="Support actions"><button data-tab="new" aria-pressed="true">New case</button><button data-tab="cases">My cases</button><button data-tab="appeals">Appeals</button></nav>
        <section class="cf02-card" data-panel="new"><h2>Create support case</h2><form data-form="case" class="cf02-form"><label>Category<select name="category" required>'.$options.'</select></label><label>Subject<input name="subject" maxlength="191" required></label><label>Description<textarea name="description" rows="6" maxlength="20000"></textarea></label><div class="cf02-grid"><label>Impact<select name="impact"><option value="single_action">One action</option><option value="account_blocked">Essential task blocked</option><option value="many_users">Many users</option></select></label><label>Urgency<select name="urgency"><option value="normal">Normal</option><option value="time_sensitive">Time-sensitive</option></select></label></div><label>Accessibility/language support<input name="accessibility" maxlength="191"></label><label class="cf02-check"><input type="checkbox" name="diagnostics_consented"> I consent to minimum contextual diagnostics.</label><button>Submit case</button></form></section>
        <section class="cf02-card" data-panel="cases" hidden><h2>My cases</h2><button data-action="cases">Refresh</button><div data-view="cases"></div><div data-view="case"></div></section>
        <section class="cf02-card" data-panel="appeals" hidden><h2>Appeals</h2><p>The original decision remains immutable; independent review and implementation confirmation are required.</p><form data-form="appeal-load" class="cf02-form"><label>Appeal ID<input name="id" placeholder="CF02-APL-…" required></label><button>Load appeal</button></form><div data-view="appeal"></div></section><div class="cf02-status" role="status" aria-live="polite"></div></div>'.self::script();
    }

    private static function safety():string{return '<div class="cf02-alert" role="note"><strong>Immediate danger:</strong> This is not an emergency or clinical queue. Use approved local emergency services. Support does not diagnose or prescribe.</div>';}

    private static function script(): string { return CompleteFrontendScript::render(); }

    private static function css():string{return '.cf02-app{max-width:64rem;margin:auto}.cf02-card{margin:1rem auto;padding:1.25rem;border:1px solid #d7d7d7;border-radius:.85rem;background:#fff;color:#1f1f1f}.cf02-tabs,.cf02-actions{display:flex;gap:.5rem;flex-wrap:wrap}.cf02-form{display:grid;gap:.75rem}.cf02-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}.cf02-form label{display:grid;gap:.35rem}.cf02-app input,.cf02-app select,.cf02-app textarea,.cf02-app button{font:inherit;min-height:44px;padding:.625rem;border:1px solid #555;border-radius:.45rem}.cf02-app :focus-visible{outline:3px solid currentColor;outline-offset:3px}.cf02-alert{padding:.8rem;border-inline-start:4px solid #a34b00;background:#fff5e8}.cf02-check{display:flex!important;gap:.5rem}.cf02-thread,[data-view="cases"]{display:grid;gap:.75rem;margin-top:1rem}.cf02-case,.cf02-message,.cf02-detail{padding:.8rem;border:1px solid #ddd;border-radius:.5rem}.cf02-status{min-height:1.5rem}@media(max-width:600px){.cf02-grid{grid-template-columns:1fr}.cf02-tabs>*{flex:1 1 100%}}@media(prefers-reduced-motion:reduce){.cf02-app *{animation:none!important;transition:none!important}}';}
}
