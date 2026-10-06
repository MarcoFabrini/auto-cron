<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use App\Tests\Factory\OrganizationFactory;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AuditLogRepositoryTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    /** Inserisce una riga di audit con un timestamp deciso (il subscriber qui non interviene: DBAL diretto). */
    private function insert(int $orgId, string $createdAt, string $entityId): void
    {
        static::getContainer()->get(Connection::class)->insert('audit_logs', [
            'organization_id' => $orgId,
            'entity_class' => 'Vehicle',
            'entity_id' => $entityId,
            'action' => 'created',
            'created_at' => $createdAt,
        ]);
    }

    public function testPaginationIsStableWhenManyRowsShareTheSameTimestamp(): void
    {
        self::bootKernel();
        $org = OrganizationFactory::createOne();
        $orgId = (int) $org->getId();
        $other = OrganizationFactory::createOne();
        // 23 righe nello stesso secondo, una più vecchia e una più recente, più una di un'altra org
        $this->insert($orgId, '2026-03-01 10:00:00', 'old');
        for ($i = 1; $i <= 23; ++$i) {
            $this->insert($orgId, '2026-03-02 10:00:00', 'same-'.$i);
        }
        $this->insert($orgId, '2026-03-03 10:00:00', 'newest');
        $this->insert((int) $other->getId(), '2026-03-02 10:00:00', 'foreign');

        $repo = static::getContainer()->get(AuditLogRepository::class);

        $pages = [];
        foreach ([0, 7, 14, 21] as $offset) {
            $pages[] = array_map(
                static fn (AuditLog $l) => ['id' => $l->getId(), 'entity' => $l->getEntityId()],
                $repo->findByOrganization($org, 7, $offset),
            );
        }
        $all = array_merge(...$pages);

        self::assertCount(25, $all, 'Tutte le righe dell\'org, nessuna dell\'altra');
        self::assertCount(25, array_unique(array_column($all, 'id')), 'Nessuna riga ripetuta tra una pagina e l\'altra');
        self::assertSame('newest', $all[0]['entity']);
        self::assertSame('old', $all[24]['entity']);
        $sameSecondIds = array_column(array_slice($all, 1, 23), 'id');
        $sorted = $sameSecondIds;
        rsort($sorted);
        self::assertSame($sorted, $sameSecondIds, 'A parità di timestamp, dal più recente (id più alto)');
    }
}
