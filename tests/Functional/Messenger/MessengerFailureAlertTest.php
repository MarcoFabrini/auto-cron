<?php

declare(strict_types=1);

namespace App\Tests\Functional\Messenger;

use App\EventSubscriber\MessengerFailureAlertSubscriber;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Test DLQ alerting subscriber (#4.3).
 */
final class MessengerFailureAlertTest extends KernelTestCase
{
    public function testLogsErrorWhenMessageWillNotRetry(): void
    {
        $logger = new class implements LoggerInterface {
            /** @var list<array{0:string,1:string,2:array<string,mixed>}> */
            public array $entries = [];
            public function emergency(\Stringable|string $message, array $context = []): void { $this->entries[] = ['emergency', (string) $message, $context]; }
            public function alert(\Stringable|string $message, array $context = []): void { $this->entries[] = ['alert', (string) $message, $context]; }
            public function critical(\Stringable|string $message, array $context = []): void { $this->entries[] = ['critical', (string) $message, $context]; }
            public function error(\Stringable|string $message, array $context = []): void { $this->entries[] = ['error', (string) $message, $context]; }
            public function warning(\Stringable|string $message, array $context = []): void { $this->entries[] = ['warning', (string) $message, $context]; }
            public function notice(\Stringable|string $message, array $context = []): void { $this->entries[] = ['notice', (string) $message, $context]; }
            public function info(\Stringable|string $message, array $context = []): void { $this->entries[] = ['info', (string) $message, $context]; }
            public function debug(\Stringable|string $message, array $context = []): void { $this->entries[] = ['debug', (string) $message, $context]; }
            public function log($level, \Stringable|string $message, array $context = []): void { $this->entries[] = [(string) $level, (string) $message, $context]; }
        };

        $sub = new MessengerFailureAlertSubscriber($logger);
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('boom'));

        $sub->onMessageFailed($event);

        self::assertCount(1, $logger->entries);
        self::assertSame('error', $logger->entries[0][0]);
        self::assertStringContainsString('messenger.dlq.alert', $logger->entries[0][1]);
        self::assertSame('boom', $logger->entries[0][2]['reason']);
    }

    public function testDoesNotLogWhenRetryStillScheduled(): void
    {
        $logger = new class implements LoggerInterface {
            /** @var list<\Stringable|string> */
            public array $entries = [];
            public function emergency(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function alert(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function critical(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function error(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function warning(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function notice(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function info(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function debug(\Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
            public function log($level, \Stringable|string $message, array $context = []): void { $this->entries[] = $message; }
        };

        $sub = new MessengerFailureAlertSubscriber($logger);
        $envelope = new Envelope(new \stdClass());
        $event = new WorkerMessageFailedEvent($envelope, 'async', new \RuntimeException('transient'));
        $event->setForRetry();

        $sub->onMessageFailed($event);

        self::assertEmpty($logger->entries, 'No log when message will be retried');
    }
}
