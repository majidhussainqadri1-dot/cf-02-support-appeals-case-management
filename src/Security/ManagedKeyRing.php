<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use RuntimeException;

/** Versioned managed encryption keys with explicit active/retired/revoked state. */
final class ManagedKeyRing
{
    /** @var array<string,array{material:string,status:string}> */
    private array $keys = [];

    /** @param array<string,string|array{material:string,status?:string}> $keys */
    public function __construct(
        private readonly string $activeKeyId,
        array $keys,
        private readonly string $provider,
        private readonly string $rotationReference
    ) {
        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $activeKeyId) !== 1) {
            throw new RuntimeException('Active encryption key identity is invalid.');
        }
        if (trim($provider) === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._:\/-]{2,191}$/', $provider) !== 1) {
            throw new RuntimeException('Managed key provider identity is invalid.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{7,191}$/', $rotationReference) !== 1) {
            throw new RuntimeException('Managed key rotation evidence is invalid.');
        }
        if ($keys === [] || count($keys) > 32) {
            throw new RuntimeException('Encryption key ring size is invalid.');
        }
        $fingerprints = [];
        foreach ($keys as $id => $definition) {
            $id = (string)$id;
            if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $id) !== 1) {
                throw new RuntimeException('Encryption key ring is malformed.');
            }
            $material = is_array($definition) ? ($definition['material'] ?? null) : $definition;
            $status = is_array($definition) ? (string)($definition['status'] ?? 'retired') : ($id === $activeKeyId ? 'active' : 'retired');
            if (!is_string($material) || strlen($material) < 32 || !in_array($status, ['active','retired','revoked'], true)) {
                throw new RuntimeException('Encryption key ring is malformed.');
            }
            $fingerprint = hash('sha256', $material);
            if (isset($fingerprints[$fingerprint])) {
                throw new RuntimeException('Encryption key material is duplicated under multiple identifiers.');
            }
            $fingerprints[$fingerprint] = true;
            $this->keys[$id] = ['material'=>$material,'status'=>$status];
        }
        if (!isset($this->keys[$activeKeyId]) || $this->keys[$activeKeyId]['status'] !== 'active') {
            throw new RuntimeException('The active encryption key must exist with active status.');
        }
        foreach ($this->keys as $id => $key) {
            if ($id !== $activeKeyId && $key['status'] === 'active') {
                throw new RuntimeException('Only one encryption key may be active.');
            }
        }
    }

    public function activeKeyId(): string { return $this->activeKeyId; }
    public function activeMaterial(): string { return $this->keys[$this->activeKeyId]['material']; }
    public function provider(): string { return $this->provider; }
    public function rotationReference(): string { return $this->rotationReference; }
    public function status(string $keyId): string { return $this->keys[$keyId]['status'] ?? 'unknown'; }

    public function material(string $keyId): string
    {
        if (!isset($this->keys[$keyId]) || $this->keys[$keyId]['status'] === 'revoked') {
            throw new RuntimeException('Encrypted payload references an unavailable or revoked key.');
        }
        return $this->keys[$keyId]['material'];
    }

    /** @return list<string> */
    public function keyIds(): array
    {
        return array_values(array_keys(array_filter($this->keys, static fn(array $key): bool => $key['status'] !== 'revoked')));
    }
}
