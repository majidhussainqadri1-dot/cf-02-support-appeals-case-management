<?php

declare(strict_types=1);
ini_set('assert.exception','1');
assert_options(ASSERT_ACTIVE,1);
assert_options(ASSERT_EXCEPTION,1);

require_once dirname(__DIR__) . '/src/Autoload.php';
\Sabri\CF02\Autoload::register(dirname(__DIR__) . '/src');
if (!function_exists('get_locale')) {
    function get_locale(): string { return 'ur_PK'; }
}
use Sabri\CF02\Infrastructure\WordPress\UrduSupplementaryTranslations;

$domain='cf-02-support-appeals-case-management';
assert(UrduSupplementaryTranslations::translate('Larger text','Larger text',$domain)==='بڑا متن');
assert(UrduSupplementaryTranslations::translate('Already translated','Larger text',$domain)==='Already translated');
assert(UrduSupplementaryTranslations::translate('Larger text','Larger text','other-plugin')==='Larger text');
assert(UrduSupplementaryTranslations::translate('Unknown label','Unknown label',$domain)==='Unknown label');
fwrite(STDOUT,"PASS V12.3 Urdu supplementary translations respect domain and compiled MO precedence\n");
