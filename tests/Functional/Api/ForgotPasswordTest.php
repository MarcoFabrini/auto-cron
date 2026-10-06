<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Factory\UserFactory;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ForgotPasswordTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    public function testExistingAndUnknownEmailGetTheSameResponse(): void
    {
        UserFactory::createOne(['email' => 'exists@test.it']);

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'exists@test.it']);
        $existing = [$this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent()];
        self::assertEmailCount(1);

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'ghost@test.it']);
        $unknown = [$this->client->getResponse()->getStatusCode(), $this->client->getResponse()->getContent()];
        self::assertEmailCount(0);

        self::assertSame($existing, $unknown);
        self::assertSame(200, $unknown[0]);
        self::assertSame(['status' => 'ok'], json_decode((string) $unknown[1], true));
    }

    public function testTheEmailIsSentOnlyAfterTheResponse(): void
    {
        UserFactory::createOne(['email' => 'late@test.it']);
        // Stesso container per tutta la richiesta, così il listener di test vede lo stesso logger delle email
        $this->client->disableReboot();
        $sentWhenResponseWasReady = null;
        static::getContainer()->get(EventDispatcherInterface::class)->addListener(
            KernelEvents::RESPONSE,
            static function () use (&$sentWhenResponseWasReady): void {
                $sentWhenResponseWasReady = count(self::getMailerEvents());
            },
            -1000,
        );

        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'late@test.it']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, $sentWhenResponseWasReady, 'Alla risposta la mail non è ancora stata spedita');
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertNotNull($message);
        self::assertEmailAddressContains($message, 'To', 'late@test.it');
    }

    public function testPerIpLimitBlocksTheEleventhRequestAcrossDifferentEmailsAndDoesNotLeakAcrossIps(): void
    {
        $this->keepRateLimiterCountersForTheWholeTest();

        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.10');
        for ($n = 1; $n <= 10; ++$n) {
            $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => "user$n@test.it"]);
            self::assertResponseStatusCodeSame(200, "richiesta $n");
        }

        // L'11ª con un indirizzo MAI visto è già bloccata: il limite per (email, IP) non c'entra
        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'user11@test.it']);
        self::assertResponseStatusCodeSame(429);
        self::assertSame('auth.too_many_attempts', $this->jsonBody()['title']);

        // Un altro IP ha il suo contatore
        $this->client->setServerParameter('REMOTE_ADDR', '203.0.113.20');
        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'user11@test.it']);
        self::assertResponseStatusCodeSame(200);
    }

    public function testPerEmailLimitStillApplies(): void
    {
        $this->keepRateLimiterCountersForTheWholeTest();
        UserFactory::createOne(['email' => 'victim@test.it']);

        for ($n = 1; $n <= 3; ++$n) {
            $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'victim@test.it']);
            self::assertResponseStatusCodeSame(200);
        }
        $this->jsonRequest('POST', '/api/auth/forgot-password', ['email' => 'victim@test.it']);

        self::assertResponseStatusCodeSame(429);
        self::assertSame('auth.too_many_attempts', $this->jsonBody()['title']);
        self::assertEmailCount(0, message: 'La 4ª richiesta non spedisce nulla');
    }
}
