<?php

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress is not loaded.\n");
    exit(1);
}

$fail = static function (string $message): never {
    fwrite(STDERR, $message . "\n");
    exit(1);
};

wp_set_current_user(1);
$state = get_option('cf02_activation_state', []);
if (!is_array($state) || ($state['status'] ?? null) !== 'ready') {
    $fail('CF-02 active-runtime evidence did not reach ready state.');
}
if ((string) get_option('cf02_schema_version', '') !== '1.3.0') {
    $fail('CF-02 schema 1.3.0 was not installed.');
}

if (!did_action('rest_api_init')) {
    do_action('rest_api_init');
}
$routes = rest_get_server()->get_routes();
foreach ([
    '/api/support/v1/cases',
    '/api/support/v1/staff/cases/search',
    '/api/support/v1/staff/appeals',
    '/api/support/v1/staff/configuration',
    '/cf02/v1/cases',
] as $route) {
    if (!isset($routes[$route])) {
        $fail('Missing runtime route: ' . $route);
    }
}

$request = new WP_REST_Request('POST', '/api/support/v1/cases');
$request->set_header('Idempotency-Key', 'runtime-smoke-0000000000000001');
$request->set_body_params([
    'category' => 'technical',
    'subject' => 'Runtime integration smoke case',
    'description' => 'A minimized test message without secret data.',
    'locale' => 'ur-PK',
    'impact' => 'single_action',
    'urgency' => 'normal',
    'diagnostics_consented' => true,
]);
$response = rest_do_request($request);
if ($response->is_error() || $response->get_status() !== 201) {
    $fail('Runtime case creation failed: ' . wp_json_encode($response->as_error()));
}
$data = $response->get_data();
$caseId = is_array($data) ? (string) ($data['case']['case_uuid'] ?? '') : '';
if (preg_match('/^CF02-[0-9A-F-]{36}$/', $caseId) !== 1) {
    $fail('Runtime returned an invalid case identity.');
}

$replay = new WP_REST_Request('POST', '/api/support/v1/cases');
$replay->set_header('Idempotency-Key', 'runtime-smoke-0000000000000001');
$replay->set_body_params($request->get_body_params());
$replayResponse = rest_do_request($replay);
$replayData = $replayResponse->get_data();
if ($replayResponse->is_error() || !is_array($replayData) || ($replayData['replayed'] ?? null) !== true
    || (string) ($replayData['case']['case_uuid'] ?? '') !== $caseId) {
    $fail('Exact intake replay was not idempotent.');
}

$collision = new WP_REST_Request('POST', '/api/support/v1/cases');
$collision->set_header('Idempotency-Key', 'runtime-smoke-0000000000000001');
$changed = $request->get_body_params();
$changed['subject'] = 'Changed payload must be rejected';
$collision->set_body_params($changed);
$collisionResponse = rest_do_request($collision);
if (!$collisionResponse->is_error()) {
    $fail('Changed idempotency replay was not rejected.');
}

$list = rest_do_request(new WP_REST_Request('GET', '/api/support/v1/cases'));
$listData = $list->get_data();
if ($list->is_error() || !is_array($listData) || count($listData['items'] ?? []) !== 1) {
    $fail('Requester-scoped case query failed.');
}

global $wpdb;
$checks = [
    'case' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cf02_cases WHERE case_uuid=%s", $caseId)),
    'sla' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cf02_sla_timers WHERE case_uuid=%s", $caseId)),
    'events' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cf02_events WHERE aggregate_ref=%s", $caseId)),
    'outbox' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cf02_outbox WHERE case_uuid=%s", $caseId)),
    'messages' => (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}cf02_messages WHERE case_uuid=%s", $caseId)),
];
foreach ($checks as $name => $count) {
    if ($count < 1) {
        $fail('Runtime persistence evidence missing: ' . $name);
    }
}

fwrite(STDOUT, wp_json_encode([
    'status' => 'passed',
    'case_id' => $caseId,
    'schema' => get_option('cf02_schema_version'),
    'runtime' => defined('CF02_VERSION') ? CF02_VERSION : null,
    'checks' => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
