<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use Sabri\CF02\Contracts\SupportContractCatalog;

/** Accessible requester/representative support, case and appeal journeys. */
final class CompleteFrontendSurfaces
{
    public static function register():void
    {
        add_shortcode('cf02_support',[self::class,'support']);add_shortcode('cf02_case_portal',[self::class,'portal']);add_shortcode('cf02_appeal_portal',[self::class,'portal']);add_action('wp_enqueue_scripts',[self::class,'assets']);
    }
    public static function assets():void
    {
        wp_register_style('cf02-complete-surfaces',false,[],CF02_VERSION);wp_enqueue_style('cf02-complete-surfaces');wp_add_inline_style('cf02-complete-surfaces',self::css());
    }
    public static function support():string
    {
        $public='<section class="cf02-card"><h1>'.esc_html__('Support and Appeals','cf-02-support-appeals-case-management').'</h1><p>'.esc_html__('Public help is available without exposing private cases. Sign in for case or appeal actions.','cf-02-support-appeals-case-management').'</p>'.self::safety().'<details><summary>'.esc_html__('Privacy boundary','cf-02-support-appeals-case-management').'</summary><p>'.esc_html__('Never submit passwords, OTPs, private keys, full payment-card data or unrelated clinical records. Native identity, payment, moderation, privacy-rights, security-incident and clinical decisions remain with their canonical owners.','cf-02-support-appeals-case-management').'</p></details></section>';
        return $public.(is_user_logged_in()?self::app():'<section class="cf02-card"><h2>'.esc_html__('Authentication required for private actions','cf-02-support-appeals-case-management').'</h2></section>');
    }
    public static function portal():string{return is_user_logged_in()?self::app():'<section class="cf02-card">'.esc_html__('A valid File 00 session is required.','cf-02-support-appeals-case-management').'</section>';}

    private static function app():string
    {
        static $done=false;if($done)return '';$done=true;
        $categories='<option value="">'.esc_html__('Choose category','cf-02-support-appeals-case-management').'</option>';foreach(SupportContractCatalog::categories() as $category){$categories.='<option value="'.esc_attr($category).'">'.esc_html(ucwords(str_replace('_',' ',$category))).'</option>';}
        $owners='<option value="">'.esc_html__('No linked platform object','cf-02-support-appeals-case-management').'</option>';foreach(SupportContractCatalog::nativeOwnerKeys() as $owner){$owners.='<option value="'.esc_attr($owner).'">'.esc_html(ucwords(str_replace('_',' ',$owner))).'</option>';}
        [$initialCase,$initialAppeal]=self::routeContext();
        return '<div class="cf02-app" data-cf02-complete data-endpoint="'.esc_url(rest_url('api/support/v1')).'" data-nonce="'.esc_attr(wp_create_nonce('wp_rest')).'" data-initial-case="'.esc_attr($initialCase).'" data-initial-appeal="'.esc_attr($initialAppeal).'">
        <nav class="cf02-tabs" aria-label="'.esc_attr__('Support actions','cf-02-support-appeals-case-management').'"><button data-tab="new" aria-pressed="true">'.esc_html__('New case','cf-02-support-appeals-case-management').'</button><button data-tab="cases">'.esc_html__('My cases','cf-02-support-appeals-case-management').'</button><button data-tab="appeals">'.esc_html__('Appeals','cf-02-support-appeals-case-management').'</button></nav>
        <section class="cf02-card" data-panel="new"><h2>'.esc_html__('Create support case','cf-02-support-appeals-case-management').'</h2><form data-form="case" class="cf02-form"><label>'.esc_html__('Category','cf-02-support-appeals-case-management').'<select name="category" required>'.$categories.'</select></label><label>'.esc_html__('Issue type or subcategory','cf-02-support-appeals-case-management').'<input name="subcategory" maxlength="80"></label><label>'.esc_html__('Subject','cf-02-support-appeals-case-management').'<input name="subject" maxlength="191" required></label><label>'.esc_html__('Description','cf-02-support-appeals-case-management').'<textarea name="description" rows="6" maxlength="20000"></textarea></label><div class="cf02-grid"><label>'.esc_html__('Impact','cf-02-support-appeals-case-management').'<select name="impact"><option value="single_action">'.esc_html__('One action','cf-02-support-appeals-case-management').'</option><option value="account_blocked">'.esc_html__('Essential task blocked','cf-02-support-appeals-case-management').'</option><option value="many_users">'.esc_html__('Many users','cf-02-support-appeals-case-management').'</option></select></label><label>'.esc_html__('Urgency','cf-02-support-appeals-case-management').'<select name="urgency"><option value="normal">'.esc_html__('Normal','cf-02-support-appeals-case-management').'</option><option value="time_sensitive">'.esc_html__('Time-sensitive','cf-02-support-appeals-case-management').'</option></select></label></div><label>'.esc_html__('Accessibility or language support','cf-02-support-appeals-case-management').'<input name="accessibility" maxlength="191"></label><fieldset><legend>'.esc_html__('Optional affected platform object','cf-02-support-appeals-case-management').'</legend><div class="cf02-grid"><label>'.esc_html__('Owner domain','cf-02-support-appeals-case-management').'<select name="object_owner">'.$owners.'</select></label><label>'.esc_html__('Object type','cf-02-support-appeals-case-management').'<input name="object_type" maxlength="80"></label><label>'.esc_html__('Object reference','cf-02-support-appeals-case-management').'<input name="object_ref" maxlength="191"></label><label>'.esc_html__('Object version','cf-02-support-appeals-case-management').'<input name="object_version" maxlength="80"></label></div></fieldset><label class="cf02-check"><input type="checkbox" name="diagnostics_consented"> '.esc_html__('I consent to minimum contextual diagnostics.','cf-02-support-appeals-case-management').'</label><button>'.esc_html__('Submit case','cf-02-support-appeals-case-management').'</button></form></section>
        <section class="cf02-card" data-panel="cases" hidden><h2>'.esc_html__('My cases','cf-02-support-appeals-case-management').'</h2><button data-action="cases">'.esc_html__('Refresh','cf-02-support-appeals-case-management').'</button><div data-view="cases"></div><div data-view="case"></div></section>
        <section class="cf02-card" data-panel="appeals" hidden><h2>'.esc_html__('Appeals','cf-02-support-appeals-case-management').'</h2><p>'.esc_html__('The original decision remains immutable; independent review and implementation confirmation are required.','cf-02-support-appeals-case-management').'</p><form data-form="appeal-load" class="cf02-form"><label>'.esc_html__('Appeal ID','cf-02-support-appeals-case-management').'<input name="id" placeholder="CF02-APL-…" required></label><button>'.esc_html__('Load appeal','cf-02-support-appeals-case-management').'</button></form><div data-view="appeal"></div></section><div class="cf02-status" role="status" aria-live="polite"></div></div>'.CompleteFrontendScript::render();
    }

    /** @return array{string,string} */
    private static function routeContext():array
    {
        $path=rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH));
        $case=preg_match('#/support/cases/(CF02-[0-9A-F-]{36})(?:/|$)#',$path,$m)===1?$m[1]:'';
        $appeal=preg_match('#/support/appeals/(CF02-APL-[A-F0-9]{20})(?:/|$)#',$path,$m)===1?$m[1]:'';
        return [$case,$appeal];
    }
    private static function safety():string{return '<div class="cf02-alert" role="note"><strong>'.esc_html__('Immediate danger:','cf-02-support-appeals-case-management').'</strong> '.esc_html__('This is not an emergency or clinical queue. Use approved local emergency services. Support does not diagnose or prescribe.','cf-02-support-appeals-case-management').'</div>';}
    private static function css():string{return '.cf02-app{max-width:64rem;margin:auto}.cf02-card{margin:1rem auto;padding:1.25rem;border:1px solid #d7d7d7;border-radius:.85rem;background:#fff;color:#1f1f1f}.cf02-tabs,.cf02-actions{display:flex;gap:.5rem;flex-wrap:wrap}.cf02-form{display:grid;gap:.75rem}.cf02-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}.cf02-form label{display:grid;gap:.35rem}.cf02-form fieldset{border:1px solid #bbb;border-radius:.5rem;padding:.75rem}.cf02-app input,.cf02-app select,.cf02-app textarea,.cf02-app button{font:inherit;min-height:44px;padding:.625rem;border:1px solid #555;border-radius:.45rem}.cf02-app :focus-visible{outline:3px solid currentColor;outline-offset:3px}.cf02-alert{padding:.8rem;border-inline-start:4px solid #a34b00;background:#fff5e8}.cf02-check{display:flex!important;gap:.5rem}.cf02-thread,[data-view="cases"]{display:grid;gap:.75rem;margin-top:1rem}.cf02-case,.cf02-message,.cf02-detail,.cf02-appeal{padding:.8rem;border:1px solid #ddd;border-radius:.5rem}.cf02-status{min-height:1.5rem}@media(max-width:600px){.cf02-grid{grid-template-columns:1fr}.cf02-tabs>*{flex:1 1 100%}}@media(prefers-reduced-motion:reduce){.cf02-app *{animation:none!important;transition:none!important}}';}
}
