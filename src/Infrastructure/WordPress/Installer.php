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
        $installed = (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
        if (version_compare($installed, Schema::VERSION, '>=')) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach (Schema::statements((string) $wpdb->prefix, (string) $wpdb->get_charset_collate()) as $sql) {
            dbDelta($sql);
        }
        update_option(self::OPTION_SCHEMA_VERSION, Schema::VERSION, false);
    }

    public static function schemaVersion(): string
    {
        return (string) get_option(self::OPTION_SCHEMA_VERSION, '0.0.0');
    }
}
