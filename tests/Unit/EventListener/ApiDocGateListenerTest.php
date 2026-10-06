<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\EventListener\ApiDocGateListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiDocGateListenerTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function docRoutes(): iterable
    {
        yield 'interfaccia' => ['app.api_doc_ui'];
        yield 'spec JSON' => ['app.api_doc_json'];
    }

    #[DataProvider('docRoutes')]
    public function testDisabledGateRejectsDocRoutes(string $route): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new ApiDocGateListener(false))($this->event($route));
    }

    #[DataProvider('docRoutes')]
    public function testEnabledGateLetsDocRoutesThrough(string $route): void
    {
        (new ApiDocGateListener(true))($this->event($route));

        $this->addToAssertionCount(1);
    }

    public function testDisabledGateIgnoresOtherRoutes(): void
    {
        $listener = new ApiDocGateListener(false);

        $listener($this->event('app.vehicle_list'));
        $listener($this->event(null));

        $this->addToAssertionCount(1);
    }

    public function testDisabledGateIgnoresSubRequests(): void
    {
        (new ApiDocGateListener(false))($this->event('app.api_doc_ui', HttpKernelInterface::SUB_REQUEST));

        $this->addToAssertionCount(1);
    }

    private function event(?string $route, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = Request::create('/api/whatever');
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type);
    }
}
