<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
use Sabri\CF02\Security\DataCipher;
use Throwable;

/** Bounded, idempotent re-encryption to the active managed key. */
final class EncryptionRotationService
{
    public function __construct(private readonly DataCipher $cipher) {}

    /** @return array{scanned:int,rotated:int,failed:int} */
    public function rotate(int $limit = 100): array
    {
        if (apply_filters('cf02_key_rotation_enabled', false) !== true) {
            return ['scanned' => 0, 'rotated' => 0, 'failed' => 0];
        }
        global $wpdb;
        $limit = max(1, min(500, $limit));
        $targets = [
            [$wpdb->prefix . 'cf02_messages', 'id', 'body_ciphertext'],
            [$wpdb->prefix . 'cf02_command_payloads', 'id', 'payload_ciphertext'],
            [$wpdb->prefix . 'cf02_outbox_payloads', 'id', 'payload_ciphertext'],
            [$wpdb->prefix . 'cf02_feedback', 'id', 'comment_ciphertext'],
            [$wpdb->prefix . 'cf02_note_revisions', 'id', 'body_ciphertext'],
        ];
        $scanned = $rotated = $failed = 0;
        foreach ($targets as [$table, $idColumn, $cipherColumn]) {
            if (preg_match('/^[A-Za-z0-9_]+$/', $table . $idColumn . $cipherColumn) !== 1) {
                throw new RuntimeException('Key rotation target is invalid.');
            }
            $activePrefix = 'v2:' . $this->cipher->activeKeyId() . ':%';
            $sql = $wpdb->prepare(
                "SELECT {$idColumn} AS row_id,{$cipherColumn} AS ciphertext FROM {$table} WHERE {$cipherColumn} IS NOT NULL AND {$cipherColumn} NOT LIKE %s ORDER BY {$idColumn} ASC LIMIT %d",
                $activePrefix,
                $limit
            );
            $rows = $wpdb->get_results($sql, ARRAY_A);
            if (!is_array($rows)) { continue; }
            foreach ($rows as $row) {
                ++$scanned;
                $encoded = (string) ($row['ciphertext'] ?? '');
                if ($encoded === '' || !$this->cipher->needsRotation($encoded)) { continue; }
                try {
                    $fromKey = $this->cipher->envelopeKeyId($encoded);
                    $new = $this->cipher->rotate($encoded);
                    $updated = $wpdb->update($table, [$cipherColumn => $new], [$idColumn => (int) $row['row_id'], $cipherColumn => $encoded]);
                    if ($updated === 1) {
                        ++$rotated;
                        $evidence = ['table' => $table, 'row_id' => (int) $row['row_id'], 'from' => $fromKey, 'to' => $this->cipher->activeKeyId()];
                        $wpdb->insert($wpdb->prefix . 'cf02_key_rotation', [
                            'table_name' => $table, 'row_id' => (int) $row['row_id'], 'from_key_id' => $fromKey,
                            'to_key_id' => $this->cipher->activeKeyId(), 'status' => 'rotated',
                            'evidence_hash' => hash('sha256', wp_json_encode($evidence)), 'rotated_at' => gmdate('Y-m-d H:i:s.u'),
                        ]);
                    } else { ++$failed; }
                } catch (Throwable) { ++$failed; }
            }
        }
        do_action('cf02_key_rotation_batch_completed', compact('scanned','rotated','failed'));
        return compact('scanned','rotated','failed');
    }
}
