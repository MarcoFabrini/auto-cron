<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

/**
 * Endpoint per healthcheck Docker / load balancer.
 *
 * Verifica connettività al DB. Ritorna 200 se OK, 503 con dettagli se down.
 *
 * NON richiede autenticazione (deve essere chiamabile da Docker e dal LB).
 */
#[OA\Tag(name: 'Health')]
#[Route('/api/health', name: 'api_health')]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    #[OA\Get(
        summary: 'Service healthcheck',
        description: 'No auth required. Checks DB connectivity. Returns 200 if healthy, 503 if down.',
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', enum: ['ok', 'degraded']),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                        new OA\Property(property: 'checks', type: 'object', properties: [
                            new OA\Property(property: 'database', properties: [
                                new OA\Property(property: 'ok', type: 'boolean'),
                                new OA\Property(property: 'error', type: 'string', nullable: true),
                            ], type: 'object'),
                        ]),
                    ],
                ),
            ),
            new OA\Response(response: 503, description: 'Database is down'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
        ];

        $allHealthy = !in_array(false, array_column($checks, 'ok'), true);

        return new JsonResponse(
            [
                'status' => $allHealthy ? 'ok' : 'degraded',
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
                'checks' => $checks,
            ],
            $allHealthy ? 200 : 503,
        );
    }

    /** @return array{ok: bool, error?: string} */
    private function checkDatabase(): array
    {
        try {
            $this->db->executeQuery('SELECT 1');
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
