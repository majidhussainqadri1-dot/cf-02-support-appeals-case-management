<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use InvalidArgumentException;

final class SchemaCompletion
{
    public const VERSION = '1.3.0';

    /** @return array<string,string> */
    public static function statements(string $prefix, string $charsetCollate): array
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Invalid WordPress table prefix.');
        }
        return [
            'key_rotation' => "CREATE TABLE {$prefix}cf02_key_rotation (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                table_name varchar(128) NOT NULL,
                row_id bigint(20) unsigned NOT NULL,
                from_key_id varchar(64) NOT NULL,
                to_key_id varchar(64) NOT NULL,
                status varchar(24) NOT NULL,
                evidence_hash char(64) NOT NULL,
                rotated_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY row_rotation (table_name,row_id,to_key_id),
                KEY status_rotated (status,rotated_at)
            ) {$charsetCollate};",
            'repair_ledger' => "CREATE TABLE {$prefix}cf02_repair_ledger (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                repair_uuid varchar(64) NOT NULL,
                actor_ref varchar(191) NOT NULL,
                approval_ref varchar(191) NOT NULL,
                status varchar(24) NOT NULL,
                evidence_json longtext NOT NULL,
                evidence_hash char(64) NOT NULL,
                repaired_at datetime(6) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY repair_uuid (repair_uuid),
                KEY repaired_at (repaired_at)
            ) {$charsetCollate};",
        ];
    }

    /** @return list<string> */
    public static function tableNames(string $prefix): array
    {
        return array_map(static fn(string $key): string => $prefix . 'cf02_' . $key, array_keys(self::statements($prefix, '')));
    }
}
