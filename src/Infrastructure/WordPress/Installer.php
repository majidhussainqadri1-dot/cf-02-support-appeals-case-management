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
            throw new RuntimeException('Installed CF-02 schema is newer than this runtime; downgrade is refused.');
        }
        if (version_compare($installed, SchemaCompletion::VERSION, '==')) {
            self::assertSchema($prefix);
            update_option('cf02_schema_last_verified_at', gmdate(DATE_ATOM), false);
            return;
        }

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

    private static function assertSchema(string $prefix): void
    {
        global $wpdb;
        if (preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1 || !method_exists($wpdb, 'get_col')) {
            throw new RuntimeException('CF-02 schema verification adapter is unavailable.');
        }
        $defects = [];
        foreach (self::statements($prefix, '') as $key => $sql) {
            $table = $prefix . 'cf02_' . $key;
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || !hash_equals($table, $found)) {
                $defects[] = $table . ':missing-table';
                continue;
            }
            $actualColumns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);
            if (!is_array($actualColumns)) {
                $defects[] = $table . ':columns-unreadable';
                continue;
            }
            $actualColumns = array_values(array_filter($actualColumns, 'is_string'));
            foreach (self::requiredColumns($sql) as $column) {
                if (!in_array($column, $actualColumns, true)) {
                    $defects[] = $table . ':missing-column:' . $column;
                }
            }
        }
        if ($defects !== []) {
            throw new RuntimeException('CF-02 schema verification failed: ' . implode(', ', $defects));
        }
    }

    /** @return list<string> */
    private static function requiredColumns(string $createSql): array
    {
        $columns = [];
        foreach (preg_split('/\R/', $createSql) ?: [] as $line) {
            $line = trim($line);
            if (preg_match('/^([a-z][a-z0-9_]*)\s+(?:bigint|smallint|tinyint|int|varchar|char|datetime|longtext|text)\b/i', $line, $matches) === 1) {
                $columns[] = strtolower($matches[1]);
            }
        }
        return array_values(array_unique($columns));
    }
}
