<?php

declare(strict_types=1);

namespace Sabri\CF02;

use Sabri\CF02\Activation\ActivationGate;
use Sabri\CF02\Activation\WordPressActivationEvidence;
use Sabri\CF02\Infrastructure\WordPress\Runtime;
use Throwable;

final class Plugin
{
    private const OPTION_INSTALLED_VERSION='cf02_installed_version';
    private const OPTION_ACTIVATION_STATE='cf02_activation_state';

    public static function activate(): void
    {
        update_option(self::OPTION_INSTALLED_VERSION, CF02_VERSION, false);
        update_option(self::OPTION_ACTIVATION_STATE, ['status'=>'pending','plan_version'=>CF02_PLAN_VERSION,'runtime_version'=>CF02_VERSION,'reason_code'=>'activation_evidence_required','updated_at'=>gmdate(DATE_ATOM)], false);
    }

    public static function boot(): void
    {
        load_plugin_textdomain('cf-02-support-appeals-case-management', false, dirname(plugin_basename(CF02_PLUGIN_FILE)).'/languages');
        $decision=(new ActivationGate(new WordPressActivationEvidence()))->evaluate();
        if(!$decision->isAllowed()){self::registerDormantState($decision->reasons());return;}
        try { Runtime::boot(); }
        catch(Throwable $error){
            $trace='CF02-BOOT-'.strtoupper(substr(hash('sha256',$error::class.'|'.$error->getCode().'|'.microtime(true)),0,16));
            self::registerDormantState(['Runtime dependencies are unavailable. Reference: '.$trace]);
            do_action('cf02_runtime_failed_closed',['trace_id'=>$trace,'error_class'=>$error::class,'error'=>$error]);
            return;
        }
        update_option(self::OPTION_INSTALLED_VERSION,CF02_VERSION,false);
        update_option(self::OPTION_ACTIVATION_STATE,['status'=>'ready','plan_version'=>CF02_PLAN_VERSION,'runtime_version'=>CF02_VERSION,'evidence'=>$decision->evidence(),'updated_at'=>gmdate(DATE_ATOM)],false);
        do_action('cf02_runtime_ready',$decision->evidence());
    }

    /** @param list<string> $reasons */
    private static function registerDormantState(array $reasons): void
    {
        $publicReasons=array_map(static fn(string $reason):string=>sanitize_text_field($reason),$reasons);
        update_option(self::OPTION_ACTIVATION_STATE,['status'=>'dormant','plan_version'=>CF02_PLAN_VERSION,'runtime_version'=>CF02_VERSION,'reasons'=>$publicReasons,'updated_at'=>gmdate(DATE_ATOM)],false);
        add_action('admin_notices',static function()use($publicReasons):void{
            if(!current_user_can('manage_options'))return;
            printf('<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',esc_html__('CF-02 remains safely dormant until its activation gates pass.','cf-02-support-appeals-case-management'),esc_html(implode(' ',$publicReasons)));
        });
        add_filter('site_status_tests',static function(array $tests)use($publicReasons):array{
            $tests['direct']['cf02_activation_gates']=['label'=>__('CF-02 activation gates','cf-02-support-appeals-case-management'),'test'=>static fn():array=>['label'=>__('CF-02 is dormant by design','cf-02-support-appeals-case-management'),'status'=>'recommended','badge'=>['label'=>__('Conditional module','cf-02-support-appeals-case-management'),'color'=>'blue'],'description'=>'<p>'.esc_html(implode(' ',$publicReasons)).'</p>','actions'=>'','test'=>'cf02_activation_gates']];return $tests;
        });
    }
}
