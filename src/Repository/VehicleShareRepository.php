<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleShare>
 */
class VehicleShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleShare::class);
    }

    public function findForUserAndVehicle(User $user, Vehicle $vehicle): ?VehicleShare
    {
        return $this->findOneBy(['user' => $user, 'vehicle' => $vehicle]);
    }

    /** @return list<VehicleShare> */
    public function findByVehicle(Vehicle $vehicle): array
    {
        return $this->findBy(['vehicle' => $vehicle], ['createdAt' => 'ASC']);
    }
}
