<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use RuntimeException;

final class DataCipher
{
    private ManagedKeyRing $keyRing;

    public function __construct(ManagedKeyRing|string $keyMaterial)
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The sodium extension is unavailable.');
        }
        if ($keyMaterial instanceof ManagedKeyRing) {
            $this->keyRing = $keyMaterial;
            return;
        }
        // Test-only compatibility. Runtime never uses this branch.
        $this->keyRing = new ManagedKeyRing('test-v1', ['test-v1' => $keyMaterial], 'test-fixture', 'test-rotation');
    }

    public function encrypt(string $plaintext): string
    {
        $keyId = $this->keyRing->activeKeyId();
        $key = $this->derive($this->keyRing->activeMaterial());
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return 'v2:' . $keyId . ':' . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        if (str_starts_with($encoded, 'v2:')) {
            $parts = explode(':', $encoded, 3);
            if (count($parts) !== 3 || preg_match('/^[A-Za-z0-9._-]{3,64}$/', $parts[1]) !== 1) {
                throw new RuntimeException('Malformed encrypted payload envelope.');
            }
            return $this->open($parts[2], $this->derive($this->keyRing->material($parts[1])));
        }
        if (str_starts_with($encoded, 'v1:')) {
            // Controlled migration support: old records use the active material.
            return $this->open(substr($encoded, 3), $this->derive($this->keyRing->activeMaterial()));
        }
        throw new RuntimeException('Unsupported encrypted payload version.');
    }

    public function activeKeyId(): string { return $this->keyRing->activeKeyId(); }

    public function envelopeKeyId(string $encoded): string
    {
        if (str_starts_with($encoded, 'v2:')) { return explode(':', $encoded, 3)[1] ?? 'unknown'; }
        return str_starts_with($encoded, 'v1:') ? 'legacy-v1' : 'unknown';
    }

    public function needsRotation(string $encoded): bool
    {
        return !str_starts_with($encoded, 'v2:' . $this->keyRing->activeKeyId() . ':');
    }

    public function rotate(string $encoded): string
    {
        return $this->needsRotation($encoded) ? $this->encrypt($this->decrypt($encoded)) : $encoded;
    }

    private function derive(string $material): string
    {
        if (strlen($material) < 32) {
            throw new RuntimeException('Encryption key material is insufficient.');
        }
        return sodium_crypto_generichash($material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private function open(string $base64, string $key): string
    {
        $raw = base64_decode($base64, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Malformed encrypted payload.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            throw new RuntimeException('Encrypted payload authentication failed.');
        }
        return $plaintext;
    }
}
