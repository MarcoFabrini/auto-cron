<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\SecretCipher;
use PHPUnit\Framework\TestCase;

final class SecretCipherTest extends TestCase
{
    public function testRoundtrip(): void
    {
        $cipher = new SecretCipher('test-secret');
        $encrypted = $cipher->encrypt('s3cr3t-smtp-pw');

        self::assertNotSame('s3cr3t-smtp-pw', $encrypted);
        self::assertSame('s3cr3t-smtp-pw', $cipher->decrypt($encrypted));
    }

    public function testEncryptionIsNonDeterministic(): void
    {
        $cipher = new SecretCipher('test-secret');
        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'), 'Nonce random → output diverso a ogni chiamata');
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $encrypted = (new SecretCipher('secret-a'))->encrypt('payload');

        $this->expectException(\RuntimeException::class);
        (new SecretCipher('secret-b'))->decrypt($encrypted);
    }

    public function testDecryptGarbageFails(): void
    {
        $this->expectException(\RuntimeException::class);
        (new SecretCipher('test-secret'))->decrypt('non-base64!!!');
    }
}
