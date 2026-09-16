<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;

final class Installer
{
    private const OPTION_SCHEMA_VERSION = 'cf02_schema_version';

    public static function install(): void
    {
        global $wpdb;
        if (!is_object($wpdb) || !isset($wpdb->prefix) || !method_exists($wpdb, 'get_charset_collate')) {
            throw new RuntimeException('WordPress database adapter is unavailable.');
        }

        $prefix = (string) $wpdb->prefix;
        $installed = (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
        if (version_compare($installed, SchemaCompletion::VERSION, '>')) {
            throw new RuntimeException(sprintf(
                'CF-02 database schema %s is newer than runtime schema %s; downgrade is blocked until compatibility is proven.',
                $installed,
                SchemaCompletion::VERSION
            ));
        }

        // dbDelta is deliberately re-run even when the version option already matches. It is
        // idempotent and repairs partial/stale tables before structural verification.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (self::statements($prefix, (string) $wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }

        self::assertSchema($prefix);
        update_option(self::OPTION_SCHEMA_VERSION, SchemaCompletion::VERSION, false);
        update_option('cf02_schema_last_verified_at', gmdate(DATE_ATOM), false);
    }

    public static function schemaVersion(): string
    {
        return (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
    }

    /** @return array<string,string> */
    public static function statements(string $prefix, string $charsetCollate): array
    {
        return array_merge(
            Schema::statements($prefix, $charsetCollate),
            SchemaExtension::statements($prefix, $charsetCollate),
            SchemaCompletion::statements($prefix, $charsetCollate)
        );
    }

    /** @return list<string> */
    public static function tableNames(string $prefix): array
    {
        return array_map(
            static fn (string $key): string => $prefix . 'cf02_' . $key,
            array_keys(self::statements($prefix, ''))
        );
    }

    /** @return list<array{table:string,type:string,name:string}> */
    public static function schemaIssues(string $prefix): array
    {
        global $wpdb;
        $issues = [];
        foreach (self::statements($prefix, '') as $key => $statement) {
            $table = $prefix . 'cf02_' . $key;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || !hash_equals($table, $found)) {
                $issues[] = ['table' => $table, 'type' => 'table_missing', 'name' => $table];
                continue;
            }
            $expected = self::expectedStructure($statement);
            $columns = $wpdb->get_results('SHOW COLUMNS FROM `' . $table . '`', ARRAY_A);
            $actualColumns = [];
            if (is_array($columns)) {
                foreach ($columns as $column) {
                    if (is_array($column) && is_string($column['Field'] ?? null)) {
                        $actualColumns[(string) $column['Field']] = true;
                    }
                }
            }
            foreach ($expected['columns'] as $column) {
                if (!isset($actualColumns[$column])) {
                    $issues[] = ['table' => $table, 'type' => 'column_missing', 'name' => $column];
                }
            }
            $indexes = $wpdb->get_results('SHOW INDEX FROM `' . $table . '`', ARRAY_A);
            $actualIndexes = [];
            if (is_array($indexes)) {
                foreach ($indexes as $index) {
                    if (is_array($index) && is_string($index['Key_name'] ?? null)) {
                        $actualIndexes[(string) $index['Key_name']] = true;
                    }
                }
            }
            foreach ($expected['indexes'] as $index) {
                if (!isset($actualIndexes[$index])) {
                    $issues[] = ['table' => $table, 'type' => 'index_missing', 'name' => $index];
                }
            }
        }
        return $issues;
    }

    /** @return array{columns:list<string>,indexes:list<string>} */
    private static function expectedStructure(string $statement): array
    {
        $columns = [];
        $indexes = [];
        foreach (preg_split('/\R/', $statement) ?: [] as $line) {
            $line = trim($line, " \t\n\r\0\x0B,");
            if ($line === '' || str_starts_with($line, 'CREATE TABLE') || str_starts_with($line, ')')) {
                continue;
            }
            if (preg_match('/^PRIMARY\s+KEY\s*\(/i', $line) === 1) {
                $indexes[] = 'PRIMARY';
                continue;
            }
            if (preg_match('/^(?:UNIQUE\s+KEY|KEY)\s+([A-Za-z0-9_]+)\s*\(/i', $line, $match) === 1) {
                $indexes[] = $match[1];
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_]+)\s+[A-Za-z]/', $line, $match) === 1) {
                $columns[] = $match[1];
            }
        }
        return ['columns' => array_values(array_unique($columns)), 'indexes' => array_values(array_unique($indexes))];
    }

    private static function assertSchema(string $prefix): void
    {
        $issues = self::schemaIssues($prefix);
        if ($issues !== []) {
            $labels = array_map(static fn (array $issue): string => $issue['table'] . ':' . $issue['type'] . ':' . $issue['name'], $issues);
            throw new RuntimeException('CF-02 schema verification failed: ' . implode(', ', $labels));
        }
    }
}
