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
        if (version_compare($installed, SchemaCompletion::VERSION, '>=')) {
            self::assertSchema($prefix);
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
        $missing = [];
        foreach (self::tableNames($prefix) as $table) {
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if (!is_string($found) || !hash_equals($table, $found)) {
                $missing[] = $table;
            }
        }
        if ($missing !== []) {
            throw new RuntimeException('CF-02 schema verification failed: ' . implode(', ', $missing));
        }
    }
}
