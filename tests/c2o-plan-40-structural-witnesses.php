<?php

declare(strict_types=1);

/**
 * Forty plan-to-code structural witnesses.
 * Checks that all 34 mandatory CF02-FR contracts and 6 central/cross-file
 * invariants still have callable code paths. Existence/traceability != full
 * runtime, staging, security, accessibility, or provider acceptance.
 */
require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');

$root = dirname(__DIR__);
$requirements = (string)file_get_contents($root . '/docs/REQUIREMENTS.md');
$trace = (string)file_get_contents($root . '/docs/TRACEABILITY.md');
$base = 'Sabri\\CF02\\';
$classes = [
    'C'=>'Infrastructure\\WordPress\\ComprehensiveRestController',
    'O'=>'Infrastructure\\WordPress\\OperationsRepository',
    'W'=>'Infrastructure\\WordPress\\RuntimeWorker',
    'P'=>'Infrastructure\\WordPress\\ProviderWebhookController',
    'G'=>'Infrastructure\\WordPress\\GuestIntakeController',
    'L'=>'Security\\ProgressiveRateLimiter',
    'T'=>'Intake\\TriagePolicy',
    'E'=>'Appeal\\AppealEligibilityPolicy',
    'V'=>'Appeal\\ReviewerAssignmentPolicy',
    'D'=>'Governance\\AdverseDecisionNotice',
    'I'=>'Integration\\ImplementationReconciler',
    'A'=>'Authorization\\PurposeBoundAccessPolicy',
    'K'=>'Knowledge\\KnowledgeSuggestionService',
    'H'=>'Knowledge\\KnowledgeArticle',
    'X'=>'Evidence\\SecureExportService',
    'U'=>'Automation\\AutomationGuard',
    'Z'=>'Automation\\AutomationAction',
    'S'=>'Governance\\ServiceEqualityPolicy',
    'N'=>'Governance\\SupportParityAudit',
    'F'=>'Authorization\\WordPressPrincipalContextFactory',
    'R'=>'Contracts\\RequiredCompanionContracts',
    'B'=>'Infrastructure\\WordPress\\Installer',
    'M'=>'Migration\\DualReadReconciler',
    'Q'=>'Infrastructure\\WordPress\\RouteRegistrar',
    'Y'=>'Safety\\EmergencyRunbookRegistry',
    'J'=>'Activation\\ActivationGate',
    'O2'=>'Activation\\OperationalEvidenceRegistry',
];
/** [source-code witness 1, witness 2] for CF02-FR-001..034, respectively. */
$functional = [
 ['C:createCase','G:issueContinuation'],
 ['P:inbound','O:recordInboundReceipt'],
 ['O:enqueueDelivery','C:createCase'],
 ['O:mergeCases','O:splitMergedCase'],
 ['G:consumeRateLimit','L:decide'],
 ['C:triageCase','T:decideAt'],
 ['C:workbench','O:caseProjection'],
 ['C:assignCase','O:assignCase'],
 ['C:addRequesterMessage','O:appendMessage'],
 ['C:editInternalNote','O:reviseInternalNote'],
 ['O:createAttachment','O:recordAttachmentScan'],
 ['C:resolveCase','C:closeCase'],
 ['O:slaPolicy','O:ensureSlaTimer'],
 ['O:pauseSla','O:resumeSla'],
 ['W:processSla','O:slaAtRisk'],
 ['O:backlogHealth','C:backlogHealth'],
 ['O:linkMajorIncident','C:linkMajorIncident'],
 ['C:appealEligibility','E:decide'],
 ['C:assignAppealReviewer','V:assign'],
 ['O:appealDossierData','O:submitAppeal'],
 ['C:recordAppealDecision','D:projection'],
 ['C:confirmAppealImplementation','I:reconcile'],
 ['C:submitAppeal','C:reopenAppeal'],
 ['A:decide','O:caseForActor'],
 ['O:recordAttachmentRedaction','O:issueAttachmentToken'],
 ['O:searchAuthorized','C:searchCases'],
 ['O:recordQuality','C:recordQuality'],
 ['K:suggest','H:isUsable'],
 ['O:addFeedback','C:submitFeedback'],
 ['O:stageConfiguration','O:rollbackConfiguration'],
 ['X:build','O:applyHold'],
 ['W:processOutbox','W:processEvents'],
 ['U:decide','Z:cases'],
 ['O:purgeCase','O:dueRetention']
];
$central = [
 ['CEN-OWN, CV-281 donor-neutral support','S:assertNoPrivilegeSignals','N:evaluate'],
 ['File 00/File 02 identity and companion gates','F:current','R:validate'],
 ['CV-283 schema/migration truth','B:schemaDefects','M:compare'],
 ['File 20 single route/shell','Q:register','Q:contracts'],
 ['CV-285 incident and human override','Y:classifyText','U:decide'],
 ['CEN-REL operational activation gates','J:evaluate','O2:validate']
];
if (count($functional)!==34 || count($central)!==6) {
    fwrite(STDERR,"Review configuration must contain exactly 34 + 6 requirements.\n"); exit(2);
}
$failed = [];
$witness = static function(string $id) use($classes,$base): bool {
    [$short,$method] = explode(':',$id,2);
    $name=$classes[$short]??null;
    return $name!==null && method_exists($base.$name,$method)
        && !(new ReflectionMethod($base.$name,$method))->isAbstract();
};
$assert = static function(string $name,bool $pass) use (&$failed):void{
    $i=$GLOBALS['cf02_witness_i']=($GLOBALS['cf02_witness_i']??0)+1;
    fwrite($pass?STDOUT:STDERR,sprintf("%s %02d/40 %s\n",$pass?'PASS':'FAIL',$i,$name));
    if(!$pass)$failed[]=$name;
};
foreach($functional as $i=>$pair) {
    $id=sprintf('CF02-FR-%03d',$i+1);
    $ok=str_contains($requirements,'| '.$id.' |')
        && str_contains($trace,'| '.$id.' |')
        && $witness($pair[0]) && $witness($pair[1]);
    $assert($id.' implementation method pair',$ok);
}
foreach($central as [$name,$first,$second]){
    $assert($name.' cross-plan method pair',$witness($first)&&$witness($second));
}
printf("CF-02 plan-to-code: %d/40 source-method witnesses pass, %d missing; production evidence separate.\n",40-count($failed),count($failed));
exit($failed===[]?0:1);
