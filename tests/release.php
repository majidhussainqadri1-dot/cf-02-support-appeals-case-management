<?php

declare(strict_types=1);

ini_set('assert.exception', '1');
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_EXCEPTION, 1);

$root = dirname(__DIR__);
$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures[] = $name . ': ' . $exception->getMessage();
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
};

$manifest = json_decode((string) file_get_contents($root . '/release/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

$test('release identity is aligned across manifest plugin readme and schema', static function () use ($root, $manifest): void {
    $plugin = (string) file_get_contents($root . '/cf-02-support-appeals-case-management.php');
    $readme = (string) file_get_contents($root . '/readme.txt');
    $schema = (string) file_get_contents($root . '/src/Infrastructure/WordPress/SchemaExtension.php');
    $version = (string) $manifest['plugin_version'];
    assert(str_contains($plugin, '* Version: ' . $version));
    assert(str_contains($plugin, "define('CF02_VERSION', '" . $version . "')"));
    assert(str_contains($plugin, "define('CF02_PLAN_VERSION', '" . $manifest['plan_version'] . "')"));
    assert(str_contains($readme, 'Stable tag: ' . $version));
    assert(str_contains($schema, "public const VERSION = '" . $manifest['database_schema_version'] . "';"));
});

$test('release package is explicit allowlist and excludes development surfaces', static function () use ($manifest): void {
    $rootFiles = $manifest['package_root_files'] ?? null;
    $directories = $manifest['package_directories'] ?? null;
    $forbidden = $manifest['forbidden_package_paths'] ?? null;
    assert(is_array($rootFiles) && is_array($directories) && is_array($forbidden));
    foreach (['cf-02-support-appeals-case-management.php', 'readme.txt', 'uninstall.php', 'LICENSE'] as $required) {
        assert(in_array($required, $rootFiles, true));
    }
    assert(in_array('src', $directories, true));
    foreach (['.git', '.github', '.env', 'build', 'docs', 'tests', 'release', 'vendor', 'node_modules'] as $excluded) {
        assert(in_array($excluded, $forbidden, true));
    }
});

$test('default uninstall is non destructive and clears every scheduler hook', static function () use ($root): void {
    $uninstall = strtolower((string) file_get_contents($root . '/uninstall.php'));
    foreach (['cf02_process_outbox', 'cf02_process_retention', 'cf02_process_reconciliation'] as $hook) {
        assert(str_contains($uninstall, $hook));
    }
    foreach (['drop table', "delete_option('cf02_", 'delete_user_meta(', 'delete from'] as $destructive) {
        assert(!str_contains($uninstall, $destructive));
    }
});

$test('release status remains truthful and external evidence gates remain explicit', static function () use ($manifest): void {
    assert($manifest['release_status'] === 'packaged-candidate-not-staging-accepted');
    $gates = $manifest['external_acceptance_gates'] ?? [];
    assert(is_array($gates) && count($gates) >= 8);
    foreach (['hostinger-staging-install-upgrade-migration', 'backup-restore-and-rollback-rehearsal', 'founder-exact-artifact-approval'] as $gate) {
        assert(in_array($gate, $gates, true));
    }
});

$test('release evidence and operational runbooks are present', static function () use ($root): void {
    foreach ([
        'build/package.py',
        'build/verify_package.py',
        'build/security_scan.py',
        'docs/PRODUCTION-READINESS.md',
        'docs/STAGING-ACCEPTANCE.md',
        'docs/BACKUP-RESTORE-ROLLBACK.md',
        'docs/RELEASE-PROCESS.md',
        'docs/SECURITY-TEST-PLAN.md',
    ] as $required) {
        assert(is_file($root . '/' . $required), $required . ' is missing');
    }
});

if ($failures !== []) {
    exit(1);
}

fwrite(STDOUT, "All CF-02 release engineering tests passed.\n");
