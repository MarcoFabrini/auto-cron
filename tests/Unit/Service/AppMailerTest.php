<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AppMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use App\Tests\Support\SpyLogger;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class AppMailerTest extends TestCase
{
    public function testNullTransportStillSendsButWarnsThatNothingIsDelivered(): void
    {
        $logger = new SpyLogger();
        $mailer = $this->createMock(MailerInterface::class);
        $email = (new Email())->from('a@test.it')->to('b@test.it')->subject('Scadenza: Tagliando')->text('x');
        $mailer->expects(self::once())->method('send')->with($email);

        (new AppMailer($mailer, $logger, 'null://null'))->send($email);

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0]['level']);
        self::assertStringContainsString('MAILER_DSN', $logger->records[0]['message']);
        self::assertSame(['subject' => 'Scadenza: Tagliando'], $logger->records[0]['context'], 'Nessun indirizzo nel log');
    }

    #[DataProvider('realTransports')]
    public function testRealTransportDoesNotWarn(string $dsn): void
    {
        $logger = new SpyLogger();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send');

        (new AppMailer($mailer, $logger, $dsn))->send((new Email())->from('a@test.it')->to('b@test.it')->subject('S')->text('x'));

        self::assertSame([], $logger->records);
    }

    /** @return iterable<string, array{string}> */
    public static function realTransports(): iterable
    {
        yield 'smtp' => ['smtp://user:secret@smtp.example.com:587'];
        yield 'smtp local' => ['smtp://mailpit:1025'];
        yield 'sendmail' => ['sendmail://default'];
    }
}
