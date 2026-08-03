<?php

declare(strict_types=1);

namespace Sabri\CF02\Security;

use RuntimeException;

final class DataCipher
{
    private string $key;

    public function __construct(string $keyMaterial)
    {
        if (strlen($keyMaterial) < 32 || !function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('Secure encryption key material or sodium extension is unavailable.');
        }
        $this->key = sodium_crypto_generichash($keyMaterial, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return 'v1:' . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $encoded): string
    {
        if (!str_starts_with($encoded, 'v1:')) {
            throw new RuntimeException('Unsupported encrypted payload version.');
        }
        $raw = base64_decode(substr($encoded, 3), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Malformed encrypted payload.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        if ($plaintext === false) {
            throw new RuntimeException('Encrypted payload authentication failed.');
        }
        return $plaintext;
    }
}
