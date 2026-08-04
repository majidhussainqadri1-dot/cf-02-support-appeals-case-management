<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use RuntimeException;

/** Versioned encryption key ring with rotation and retired-key decryption. */
final class ManagedKeyRing
{
    /** @var array<string,string> */
    private array $keys;

    /** @param array<string,string> $keys */
    public function __construct(
        private readonly string $activeKeyId,
        array $keys,
        private readonly string $provider,
        private readonly string $rotationReference
    ) {
        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $activeKeyId) !== 1 || !isset($keys[$activeKeyId])) {
            throw new RuntimeException('Active encryption key identity is invalid.');
        }
        if ($provider === '' || $rotationReference === '') {
            throw new RuntimeException('Managed key provider and rotation evidence are required.');
        }
        foreach ($keys as $id => $material) {
            if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', (string) $id) !== 1 || !is_string($material) || strlen($material) < 32) {
                throw new RuntimeException('Encryption key ring is malformed.');
            }
        }
        $this->keys = $keys;
    }

    public function activeKeyId(): string { return $this->activeKeyId; }
    public function activeMaterial(): string { return $this->keys[$this->activeKeyId]; }
    public function provider(): string { return $this->provider; }
    public function rotationReference(): string { return $this->rotationReference; }

    public function material(string $keyId): string
    {
        if (!isset($this->keys[$keyId])) {
            throw new RuntimeException('Encrypted payload references an unavailable or revoked key.');
        }
        return $this->keys[$keyId];
    }

    /** @return list<string> */
    public function keyIds(): array { return array_keys($this->keys); }
}
