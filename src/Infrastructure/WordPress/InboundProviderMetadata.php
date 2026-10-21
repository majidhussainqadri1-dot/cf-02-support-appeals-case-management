<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;

/**
 * Preserve exact signed provider replay/actor identifiers.
 *
 * Reject malformed or over-column-width values rather than silently
 * sanitizing/truncating an event ID and creating a new canonical identity.
 */
final class InboundProviderMetadata
{
    /**
     * @param array<string,mixed> $payload
     * @return array{source_owner:string,external_event_id:string,sender_ref:string,sender_trust:string}
     */
    public static function fromPayload(array $payload): array
    {
        $result = [];
        foreach (['source_owner'=>64, 'external_event_id'=>191, 'sender_ref'=>191] as $field => $maxBytes) {
            $raw = $payload[$field] ?? null;
            if (!is_string($raw) || $raw === '' || $raw !== trim($raw)
                || strlen($raw) > $maxBytes
                || preg_match('//u', $raw) !== 1
                || preg_match('/[\x00-\x1F\x7F]/', $raw) === 1) {
                throw new RuntimeException('Signed inbound provider identity field is invalid.');
            }
            $result[$field] = $raw;
        }
        $trust = array_key_exists('sender_trust', $payload) ? $payload['sender_trust'] : 'unverified';
        if (!is_string($trust) || !in_array($trust, ['verified','unverified','system'], true)) {
            throw new RuntimeException('Signed inbound sender trust is invalid.');
        }
        $result['sender_trust'] = $trust;
        return $result;
    }
}
