<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * `/api/doc` e `/api/doc.json` rispondono solo con API_DOC_ENABLED=true (nei test è true da .env.test);
 * da spente sono indistinguibili da una rotta inesistente: stesso 404 Problem Details, qualunque Accept.
 * Non serve il database: nessuna delle due richieste lo tocca.
 */
final class ApiDocAccessTest extends WebTestCase
{
    private ?string $previousFlag = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFlag = $_SERVER['API_DOC_ENABLED'] ?? null;
    }

    protected function tearDown(): void
    {
        $this->setFlag($this->previousFlag);
        parent::tearDown();
    }

    /** @return iterable<string, array{string, string}> */
    public static function docRequests(): iterable
    {
        foreach (['/api/doc', '/api/doc.json'] as $path) {
            foreach (['*/*', 'text/html,application/xhtml+xml'] as $accept) {
                yield "$path ($accept)" => [$path, $accept];
            }
        }
    }

    #[DataProvider('docRequests')]
    public function testDisabledDocIsAProblemDetailsNotFound(string $path, string $accept): void
    {
        $this->setFlag('false');
        $client = static::createClient();

        $client->request('GET', $path, server: ['HTTP_ACCEPT' => $accept]);

        self::assertResponseStatusCodeSame(404);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertSame(
            ['type' => 'about:blank', 'title' => 'http.404', 'status' => 404],
            json_decode((string) $client->getResponse()->getContent(), true),
        );
    }

    public function testDisabledDocLooksLikeAnyUnknownRoute(): void
    {
        $this->setFlag('false');
        $client = static::createClient();

        $client->request('GET', '/api/does-not-exist');
        $unknown = $client->getResponse()->getContent();
        $client->request('GET', '/api/doc.json');

        self::assertResponseStatusCodeSame(404);
        self::assertSame($unknown, $client->getResponse()->getContent());
    }

    public function testEnabledDocServesTheInteractiveUi(): void
    {
        $this->setFlag('true');
        $client = static::createClient();

        $client->request('GET', '/api/doc', server: ['HTTP_ACCEPT' => 'text/html']);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/html; charset=UTF-8');
        $html = (string) $client->getResponse()->getContent();
        // Script dalla stessa origine (CSP script-src 'self') e nessun bootstrap inline
        self::assertStringContainsString('src="/bundles/nelmioapidoc/scalar/scalar.standalone.js"', $html);
        self::assertSame(0, preg_match('#<script(?![^>]*\bsrc=)(?![^>]*type="application/json")#', $html));
        self::assertStringNotContainsString('cdn.jsdelivr.net', $html);
    }

    public function testEnabledDocJsonIsPublicAndHasARelativeServer(): void
    {
        $this->setFlag('true');
        $client = static::createClient();

        $client->request('GET', '/api/doc.json');

        self::assertResponseIsSuccessful();
        $spec = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($spec);
        self::assertSame([['url' => '/', 'description' => 'This instance']], $spec['servers']);
    }

    #[DataProvider('flagValues')]
    public function testOtherApiRoutesAreUnaffected(string $flag): void
    {
        $this->setFlag($flag);
        $client = static::createClient();

        $client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);

        $client->request('GET', '/api/health');
        self::assertResponseIsSuccessful();
    }

    /** @return iterable<string, array{string}> */
    public static function flagValues(): iterable
    {
        yield 'spenta' => ['false'];
        yield 'accesa' => ['true'];
    }

    /** Sovrascrive API_DOC_ENABLED prima che il kernel (e il suo contenitore) venga avviato. */
    private function setFlag(?string $value): void
    {
        if ($value === null) {
            unset($_SERVER['API_DOC_ENABLED'], $_ENV['API_DOC_ENABLED']);
            putenv('API_DOC_ENABLED');
        } else {
            $_SERVER['API_DOC_ENABLED'] = $_ENV['API_DOC_ENABLED'] = $value;
            putenv("API_DOC_ENABLED=$value");
        }
    }
}
