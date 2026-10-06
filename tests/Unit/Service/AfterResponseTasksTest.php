<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AfterResponseTasks;
use App\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

final class AfterResponseTasksTest extends TestCase
{
    public function testTasksRunOnlyWhenTheKernelTerminatesAndOnlyOnce(): void
    {
        $tasks = new AfterResponseTasks(new SpyLogger());
        $calls = 0;
        $tasks->defer(function () use (&$calls): void { ++$calls; });

        self::assertSame(0, $calls, 'Niente prima di kernel.terminate');

        $tasks->run();
        $tasks->run();

        self::assertSame(1, $calls, 'Una seconda terminate (processo a vita lunga) non rilancia il task');
    }

    public function testAFailingTaskIsLoggedAndDoesNotStopTheNextOnes(): void
    {
        $logger = new SpyLogger();
        $tasks = new AfterResponseTasks($logger);
        $ran = false;
        $tasks->defer(static function (): void { throw new \RuntimeException('smtp down'); }, ['event' => 'password_reset_email', 'user_id' => 7]);
        $tasks->defer(function () use (&$ran): void { $ran = true; });

        $tasks->run();

        self::assertTrue($ran);
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame(['event' => 'password_reset_email', 'user_id' => 7, 'error' => 'smtp down'], $logger->records[0]['context']);
    }
}
