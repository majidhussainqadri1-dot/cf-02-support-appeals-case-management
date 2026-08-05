<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;

/** Publishes canonical routes to File 01/File 20 without creating a second shell. */
final class RouteRegistrar
{
    /** @return array<string,array<string,mixed>> */
    public static function contracts(): array
    {
        return [
            '/support'=>['surface'=>'support','privacy'=>'mixed','cache'=>'public-help/private-user','owner'=>'CF-02','renderer'=>'cf02_support'],
            '/support/cases/{id}'=>['surface'=>'case','privacy'=>'private','cache'=>'no-store','owner'=>'CF-02','renderer'=>'cf02_case_portal'],
            '/support/appeals/{id}'=>['surface'=>'appeal','privacy'=>'private','cache'=>'no-store','owner'=>'CF-02','renderer'=>'cf02_appeal_portal'],
            '/admin/support'=>['surface'=>'operations','privacy'=>'restricted','cache'=>'no-store','owner'=>'CF-02'],
            '/admin/support/cases/{id}'=>['surface'=>'workbench','privacy'=>'restricted','cache'=>'no-store','owner'=>'CF-02'],
            '/admin/support/quality'=>['surface'=>'quality','privacy'=>'restricted','cache'=>'no-store','owner'=>'CF-02'],
            '/api/support/v1/*'=>['surface'=>'api','privacy'=>'scoped','cache'=>'no-store','owner'=>'CF-02'],
        ];
    }

    public static function register(): void
    {
        add_action('init',[self::class,'publish'],20);
        add_action('cf02_republish_route_contracts',static function():void{self::publish();});
        add_filter('cf02_route_contracts',static fn(array $contracts):array=>array_merge($contracts,self::contracts()));
        add_filter('cf02_render_route_surface',[self::class,'render'],10,3);
    }

    public static function publish(): void
    {
        $receipts=[];
        foreach(self::contracts() as $route=>$contract){
            $envelope=array_merge($contract,[
                'contract_version'=>\Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION,
                'module_version'=>CF02_VERSION,'schema_version'=>SchemaCompletion::VERSION,
            ]);
            do_action('sabri_register_route_registry_contract',$route,$envelope);
            do_action('sabri_register_route_contract',$route,$envelope);
            /** @var mixed $receipt */
            $receipt=apply_filters('cf02_route_contract_receipt',null,$route,$envelope);
            $receipts[$route]=is_array($receipt)?$receipt:['accepted'=>false];
        }
        update_option('cf02_route_contract_receipts',$receipts,false);
        do_action('cf02_route_contracts_ready',self::contracts(),$receipts);
    }

    /** @param array<string,mixed> $context */
    public static function render(mixed $current,string $route,array $context=[]):mixed
    {
        $contracts=self::contracts();
        if(!isset($contracts[$route]['renderer']))return $current;
        $renderer=(string)$contracts[$route]['renderer'];
        if(!shortcode_exists($renderer))throw new RuntimeException('CF-02 route renderer is unavailable.');
        return do_shortcode('['.$renderer.']');
    }

    /** @param array<string,mixed> $receipts @return list<string> */
    public static function validateReceipts(array $receipts): array
    {
        $errors=[];$contracts=self::contracts();
        $receivedRoutes=array_keys($receipts);$expectedRoutes=array_keys($contracts);sort($receivedRoutes);sort($expectedRoutes);
        if($receivedRoutes!==$expectedRoutes){$errors[]='Route receipt set does not exactly match canonical routes.';}
        foreach($contracts as $route=>$contract){
            $receipt=$receipts[$route]??null;
            if(!is_array($receipt)||($receipt['accepted']??false)!==true){$errors[]='Route receipt is unaccepted: '.$route;continue;}
            $expected=['owner'=>'File 20','module_version'=>CF02_VERSION,'schema_version'=>SchemaCompletion::VERSION,'contract_version'=>\Sabri\CF02\Contracts\SupportContractCatalog::CONTRACT_VERSION];
            foreach($expected as $field=>$value){if(($receipt[$field]??null)!==$value)$errors[]=sprintf('Route receipt mismatch for %s: %s.',$route,$field);}
        }
        return array_values(array_unique($errors));
    }
}
