<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Cifratura simmetrica per segreti applicativi a riposo (es. password SMTP).
 * sodium crypto_secretbox (XSalsa20-Poly1305), chiave derivata da APP_SECRET:
 * cambiare APP_SECRET invalida i segreti cifrati (vanno reinseriti).
 * Formato: base64(nonce ‖ ciphertext).
 */
final class SecretCipher
{
    private readonly string $key;

    public function __construct(
        #[Autowire(param: 'kernel.secret')] string $appSecret,
    ) {
        $this->key = sodium_crypto_generichash($appSecret, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        return base64_encode($nonce.$cipher);
    }

    /**
     * @throws \RuntimeException se il payload è corrotto o la chiave è cambiata
     */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('cipher.invalid_payload');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('cipher.decrypt_failed');
        }
        return $plain;
    }
}
