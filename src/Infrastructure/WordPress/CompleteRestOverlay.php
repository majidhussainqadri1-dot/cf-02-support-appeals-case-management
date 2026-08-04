<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Sabri\CF02\Authorization\PrincipalContext;
use Sabri\CF02\Authorization\WordPressPrincipalContextFactory;
use Sabri\CF02\Search\CursorCodec;
use Throwable;

/**
 * Corrective overlay for plan-required opaque keyset pagination and repair APIs.
 * It short-circuits only the affected GET queries; all existing mutation routes
 * remain owned by ComprehensiveRestController.
 */
final class CompleteRestOverlay
{
    public function __construct(private readonly CursorCodec $cursors) {}

    public function register(): void
    {
        add_filter('rest_pre_dispatch', [$this, 'preDispatch'], 5, 3);
        add_action('rest_api_init', function (): void {
            foreach (['cf02/v1','api/support/v1'] as $namespace) {
                register_rest_route($namespace, '/staff/repair/inspect', [
                    'methods' => 'GET', 'permission_callback' => [$this, 'repairPermission'],
                    'callback' => fn (): \WP_REST_Response => new \WP_REST_Response(RepairService::inspect(), 200),
                ]);
                register_rest_route($namespace, '/staff/repair/run', [
                    'methods' => 'POST', 'permission_callback' => [$this, 'repairPermission'],
                    'callback' => [$this, 'runRepair'],
                ]);
            }
        }, 30);
    }

    public function preDispatch(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        if ($result !== null || strtoupper($request->get_method()) !== 'GET') { return $result; }
        $route = $request->get_route();
        $suffix = preg_replace('#^/(?:cf02/v1|api/support/v1)#', '', $route);
        try {
            return match ($suffix) {
                '/cases' => new \WP_REST_Response($this->myCases($request), 200),
                '/staff/queue' => new \WP_REST_Response($this->assignedQueue($request), 200),
                '/staff/cases/search' => new \WP_REST_Response($this->search($request), 200),
                '/staff/appeals' => new \WP_REST_Response($this->appeals($request), 200),
                default => null,
            };
        } catch (Throwable) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
    }

    public function repairPermission(): bool|\WP_Error
    {
        try {
            $context = $this->context();
            if (!$context->hasAnyCapability('release.evidence.read','configuration.activate')) { throw new RuntimeException('Denied'); }
            return true;
        } catch (Throwable) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
    }

    public function runRepair(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        try {
            $context = $this->context();
            if (!$context->recentlyAuthenticated($this->now())) { throw new RuntimeException('Recent authentication required'); }
            return new \WP_REST_Response(RepairService::repair($context, sanitize_text_field((string)$request->get_param('approval_ref')), $this->now()), 200);
        } catch (Throwable) {
            return new \WP_Error('cf02_not_found', __('Resource not found.', 'cf-02-support-appeals-case-management'), ['status' => 404]);
        }
    }

    /** @return array<string,mixed> */
    private function myCases(\WP_REST_Request $request): array
    {
        $context=$this->context();$this->require($context,'case.own.read','case.represented.read');
        $refs=array_values(array_unique(array_merge([$context->actorReference()],$context->representedRequesters())));
        return $this->casePage($request,$context,'my_cases',$refs,[],false);
    }

    /** @return array<string,mixed> */
    private function assignedQueue(\WP_REST_Request $request): array
    {
        $context=$this->context();$this->require($context,'queue.assigned.read','queue.specialist.read','queue.manage');
        $filters=['state'=>sanitize_key((string)$request->get_param('state'))];
        return $this->casePage($request,$context,'assigned_queue',[], $filters,true);
    }

    /** @return array<string,mixed> */
    private function search(\WP_REST_Request $request): array
    {
        $context=$this->context();$this->require($context,'case.search.scoped','queue.manage');
        $filters=[];foreach(['state','category','priority','queue_key'] as $key){$filters[$key]=sanitize_key((string)$request->get_param($key));}
        $page=$this->casePage($request,$context,'authorized_search',[], $filters,true);
        $page['hidden_counts_disclosed']=false;return $page;
    }

    /** @return array<string,mixed> */
    private function appeals(\WP_REST_Request $request): array
    {
        global $wpdb;$context=$this->context();$this->require($context,'appeal.queue.read','appeal.review');
        $scope='appeals:'.hash('sha256',$context->actorReference());$position=$this->decode($request,$scope);$where='1=1';$args=[];
        if(!$context->hasCapability('appeal.queue.read')){$where='reviewer_ref=%s';$args[]=$context->actorReference();}
        if($position!==null){$rank=(int)($position['rank']??0);$updated=(string)($position['updated_at']??'');$id=(int)($position['id']??0);if($rank<1||$rank>10||$updated===''||$id<1){throw new RuntimeException('Invalid cursor');}$where.=" AND (FIELD(state,'submitted','eligibility_review','accepted','under_review','native_decision_pending','decided','implemented','reopened','rejected','closed')>%d OR (FIELD(state,'submitted','eligibility_review','accepted','under_review','native_decision_pending','decided','implemented','reopened','rejected','closed')=%d AND (updated_at>%s OR (updated_at=%s AND id>%d))))";array_push($args,$rank,$rank,$updated,$updated,$id);}
        $take=$this->limit($request)+1;$args[]=$take;$table=$wpdb->prefix.'cf02_appeals';$rows=$wpdb->get_results($wpdb->prepare("SELECT id,appeal_uuid,case_uuid,original_decision_ref,reviewer_ref,state,outcome,native_command_ref,implementation_ref,record_version,submitted_at,updated_at FROM {$table} WHERE {$where} ORDER BY FIELD(state,'submitted','eligibility_review','accepted','under_review','native_decision_pending','decided','implemented','reopened','rejected','closed'),updated_at ASC,id ASC LIMIT %d",...$args),ARRAY_A);$rows=is_array($rows)?$rows:[];
        $states=['submitted','eligibility_review','accepted','under_review','native_decision_pending','decided','implemented','reopened','rejected','closed'];return $this->finish($rows,$take,$scope,static fn(array $r):array=>['rank'=>array_search((string)$r['state'],$states,true)+1,'updated_at'=>(string)$r['updated_at'],'id'=>(int)$r['id']]);
    }

    /** @param list<string> $requesterRefs @param array<string,string> $filters @return array<string,mixed> */
    private function casePage(\WP_REST_Request $request, PrincipalContext $context, string $kind, array $requesterRefs, array $filters, bool $staff): array
    {
        global $wpdb;$scope=$kind.':'.hash('sha256',$context->actorReference()."\0".wp_json_encode($filters));$position=$this->decode($request,$scope);$clauses=[];$args=[];
        if($requesterRefs!==[]){$clauses[]='c.requester_ref IN ('.implode(',',array_fill(0,count($requesterRefs),'%s')).')';array_push($args,...$requesterRefs);}
        foreach(['state','category','priority','queue_key'] as $field){if(($filters[$field]??'')!==''){$clauses[]="c.{$field}=%s";$args[]=$filters[$field];}}
        if($staff&&!$context->hasCapability('queue.manage')){$clauses[]='EXISTS (SELECT 1 FROM '.$wpdb->prefix.'cf02_assignments a WHERE a.case_uuid=c.case_uuid AND a.agent_ref=%s AND a.ended_at IS NULL)';$args[]=$context->actorReference();}
        if($position!==null){$rank=(int)($position['rank']??0);$updated=(string)($position['updated_at']??'');$id=(int)($position['id']??0);if($updated===''||$id<1){throw new RuntimeException('Invalid cursor');}if($staff){if($rank<1||$rank>4){throw new RuntimeException('Invalid cursor');}$clauses[]="(FIELD(c.priority,'P1','P2','P3','P4')>%d OR (FIELD(c.priority,'P1','P2','P3','P4')=%d AND (c.updated_at>%s OR (c.updated_at=%s AND c.id>%d))))";array_push($args,$rank,$rank,$updated,$updated,$id);}else{$clauses[]='(c.updated_at<%s OR (c.updated_at=%s AND c.id<%d))';array_push($args,$updated,$updated,$id);}}
        $take=$this->limit($request)+1;$args[]=$take;$where=$clauses===[]?'1=1':implode(' AND ',$clauses);$order=$staff?"FIELD(c.priority,'P1','P2','P3','P4'),c.updated_at ASC,c.id ASC":'c.updated_at DESC,c.id DESC';$table=$wpdb->prefix.'cf02_cases';$rows=$wpdb->get_results($wpdb->prepare("SELECT c.id,c.case_uuid,c.requester_ref,c.category,c.priority,c.severity,c.state,c.queue_key,c.owner_ref,c.locale,c.safe_subject,c.record_version,c.created_at,c.updated_at FROM {$table} c WHERE {$where} ORDER BY {$order} LIMIT %d",...$args),ARRAY_A);$rows=is_array($rows)?$rows:[];
        return $this->finish($rows,$take,$scope,static fn(array $r):array=>$staff?['rank'=>array_search((string)$r['priority'],['P1','P2','P3','P4'],true)+1,'updated_at'=>(string)$r['updated_at'],'id'=>(int)$r['id']]:['updated_at'=>(string)$r['updated_at'],'id'=>(int)$r['id']]);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed> */
    private function finish(array $rows,int $take,string $scope,callable $position):array{$more=count($rows)===$take;if($more){array_pop($rows);}$next=null;if($more&&$rows!==[]){$next=$this->cursors->encode($scope,$position($rows[array_key_last($rows)]));}foreach($rows as &$row){unset($row['id']);}unset($row);return ['items'=>$rows,'next_cursor'=>$next,'pagination'=>'opaque_keyset'];}
    /** @return array<string,int|string>|null */ private function decode(\WP_REST_Request $request,string $scope):?array{$v=trim((string)$request->get_param('cursor'));return $this->cursors->decode($v===''?null:$v,$scope);}
    private function limit(\WP_REST_Request $request):int{return max(1,min(100,(int)($request->get_param('limit')?:50)));}
    private function context():PrincipalContext{return (new WordPressPrincipalContextFactory())->current($this->now());}
    private function now():DateTimeImmutable{return new DateTimeImmutable('now',new DateTimeZone('UTC'));}
    private function require(PrincipalContext $c,string ...$caps):void{if(!$c->validAt($this->now())||!$c->hasAnyCapability(...$caps)){throw new RuntimeException('Denied');}}
}
