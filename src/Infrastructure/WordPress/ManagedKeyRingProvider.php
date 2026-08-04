<?php

declare(strict_types=1);

namespace Sabri\CF02\Infrastructure\WordPress;

use RuntimeException;
use Sabri\CF02\Security\ManagedKeyRing;

final class ManagedKeyRingProvider
{
    public static function current(): ManagedKeyRing
    {
        /** @var mixed $evidence */
        $evidence = apply_filters('cf02_managed_keyring', null, [
            'module' => 'CF-02',
            'runtime_version' => CF02_VERSION,
            'purpose' => 'support_case_encryption_at_rest',
        ]);
        if (!is_array($evidence) || ($evidence['ready'] ?? false) !== true) {
            throw new RuntimeException('A managed, versioned CF-02 encryption key ring is required.');
        }
        $keys = $evidence['keys'] ?? null;
        if (!is_array($keys)) {
            throw new RuntimeException('Managed key ring evidence is malformed.');
        }
        /** @var array<string,string> $keys */
        return new ManagedKeyRing(
            (string) ($evidence['active_key_id'] ?? ''),
            $keys,
            (string) ($evidence['provider'] ?? ''),
            (string) ($evidence['rotation_reference'] ?? '')
        );
    }
}
