<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\VehicleTransferException;
use PHPUnit\Framework\TestCase;

final class VehicleTransferExceptionTest extends TestCase
{
    public function testStatusesFollowTheApiConvention(): void
    {
        self::assertSame(422, VehicleTransferException::recipientInvalid()->status);
        self::assertSame(409, VehicleTransferException::sameOwner()->status);
        self::assertSame(409, VehicleTransferException::ownershipChanged()->status);
        self::assertSame('transfer.recipient_invalid', VehicleTransferException::recipientInvalid()->key);
    }

    /** Il controller passa `$e->key` a problem(): FrontendErrorKeysTest non vede le chiavi dinamiche. */
    public function testEveryRejectionKeyIsTranslatedInTheFrontend(): void
    {
        $keys = array_filter(
            (new \ReflectionClass(VehicleTransferException::class))->getConstants(),
            static fn (mixed $v): bool => \is_string($v),
        );
        self::assertCount(3, $keys);

        foreach (['it', 'en'] as $locale) {
            $data = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/frontend/src/i18n/locales/'.$locale.'.json'), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            foreach ($keys as $key) {
                [$domain, $code] = explode('.', (string) $key, 2);
                self::assertIsString($data['errors'][$domain][$code] ?? null, "errors.$key manca in $locale.json");
            }
        }
    }
}
