<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Authorization\WordPressPrincipalContextFactory;
use Throwable;

/** Capability-scoped operations dashboard authorized exclusively by File 00. */
final class CompleteAdminSurfaces
{
    public static function register():void
    {
        add_action('admin_menu',static function():void{$c=self::context();if(!$c||!self::allowed($c))return;add_menu_page('Support Operations','Support','read','cf02-support-complete',[self::class,'render'],'dashicons-sos',58);});
    }

    public static function render():void
    {
        $c=self::context();if(!$c||!self::allowed($c)){wp_die(esc_html__('Resource not found.','cf-02-support-appeals-case-management'),404);}
        echo '<div class="wrap cf02-admin" data-cf02-admin data-endpoint="'.esc_url(rest_url('api/support/v1')).'" data-nonce="'.esc_attr(wp_create_nonce('wp_rest')).'"><h1>Support Operations</h1><p>Every query and mutation is re-authorized by File 00. WordPress administrator status alone grants no support authority.</p><nav class="nav-tab-wrapper"><button class="nav-tab" data-viewkey="queue">Queue</button><button class="nav-tab" data-viewkey="search">Cases</button><button class="nav-tab" data-viewkey="appeals">Appeals</button><button class="nav-tab" data-viewkey="sla">SLA</button><button class="nav-tab" data-viewkey="quality">Quality</button><button class="nav-tab" data-viewkey="retention">Retention</button><button class="nav-tab" data-viewkey="repair">Repair</button></nav><section data-output aria-live="polite"></section><div data-status role="status" aria-live="polite"></div></div><style>'.self::css().'</style><script>'.self::script().'</script>';
    }

    private static function context():?PrincipalContext{try{$n=new DateTimeImmutable('now',new DateTimeZone('UTC'));$c=(new WordPressPrincipalContextFactory())->current($n);return $c->validAt($n)?$c:null;}catch(Throwable){return null;}}
    private static function allowed(PrincipalContext $c):bool{return $c->hasAnyCapability('queue.assigned.read','queue.specialist.read','queue.manage','appeal.queue.read','appeal.review','metrics.read','quality.manage','retention.review','release.evidence.read','configuration.activate');}

    private static function script(): string { return CompleteAdminScript::render(); }
    private static function css():string{return '.cf02-admin nav{display:flex;flex-wrap:wrap}.cf02-admin [data-output]{margin-top:1rem;overflow:auto}.cf02-admin pre{background:#fff;border:1px solid #ccd0d4;padding:1rem;white-space:pre-wrap}.cf02-admin :focus-visible{outline:3px solid currentColor;outline-offset:2px}@media(max-width:782px){.cf02-admin nav button{width:100%}}@media(prefers-reduced-motion:reduce){.cf02-admin *{animation:none!important;transition:none!important}}';}
}
