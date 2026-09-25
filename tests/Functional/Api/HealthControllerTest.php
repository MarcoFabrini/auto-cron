<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Support\ApiTestCase;

final class HealthControllerTest extends ApiTestCase
{
    public function testHealthReturnsOkWhenServicesUp(): void
    {
        $this->jsonRequest('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $body = $this->jsonBody();
        self::assertSame('ok', $body['status']);
        self::assertTrue($body['checks']['database']['ok']);
    }

    public function testHealthDoesNotRequireAuthentication(): void
    {
        // Niente token, niente cookie — deve rispondere comunque
        $this->client->request('GET', '/api/health');
        self::assertResponseStatusCodeSame(200);
    }
}
