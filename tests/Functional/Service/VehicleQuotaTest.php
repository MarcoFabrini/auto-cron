<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Repository\VehicleRepository;
use App\Service\VehicleQuota;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\VehicleFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Il tetto di veicoli per organizzazione: 0 = nessun limite, limite inclusivo, archiviati contati,
 * veicoli delle altre organizzazioni ignorati.
 */
final class VehicleQuotaTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private function quota(int $limit): VehicleQuota
    {
        self::bootKernel();

        return new VehicleQuota(static::getContainer()->get(VehicleRepository::class), $limit);
    }

    public function testZeroMeansUnlimited(): void
    {
        $org = OrganizationFactory::createOne();
        VehicleFactory::createMany(5, ['organization' => $org]);

        self::assertNull($this->quota(0)->violation($org));
    }

    public function testLimitIsInclusive(): void
    {
        $org = OrganizationFactory::createOne();
        $quota = $this->quota(3);

        VehicleFactory::createMany(2, ['organization' => $org]);
        self::assertNull($quota->violation($org), 'Il terzo veicolo ci sta');

        VehicleFactory::createOne(['organization' => $org]);
        self::assertSame(VehicleQuota::LIMIT_REACHED, $quota->violation($org), 'Il quarto no');
    }

    public function testArchivedVehiclesCount(): void
    {
        $org = OrganizationFactory::createOne();
        VehicleFactory::createOne(['organization' => $org]);
        VehicleFactory::createOne(['organization' => $org, 'archivedAt' => new \DateTimeImmutable()]);

        self::assertSame(VehicleQuota::LIMIT_REACHED, $this->quota(2)->violation($org));
    }

    public function testOtherOrganizationsVehiclesDoNotCount(): void
    {
        $org = OrganizationFactory::createOne();
        $other = OrganizationFactory::createOne();
        VehicleFactory::createOne(['organization' => $org]);
        VehicleFactory::createMany(4, ['organization' => $other]);

        self::assertNull($this->quota(2)->violation($org));
    }

    public function testRepositoryCountIsTenantScopedAndIncludesArchived(): void
    {
        $org = OrganizationFactory::createOne();
        $other = OrganizationFactory::createOne();
        VehicleFactory::createOne(['organization' => $org]);
        VehicleFactory::createOne(['organization' => $org, 'archivedAt' => new \DateTimeImmutable()]);
        VehicleFactory::createOne(['organization' => $other]);
        self::bootKernel();

        $repo = static::getContainer()->get(VehicleRepository::class);
        self::assertSame(2, $repo->countByOrganization($org));
        self::assertSame(1, $repo->countByOrganization($other));
    }
}
