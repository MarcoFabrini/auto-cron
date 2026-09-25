<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Attachment;
use App\Enum\AttachmentEntityType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Attachment>
 */
final class AttachmentFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Attachment::class;
    }

    protected function defaults(): array
    {
        $vehicle = VehicleFactory::createOne();
        return [
            'organization' => $vehicle->getOrganization(),
            'entityType' => AttachmentEntityType::VEHICLE,
            'entityId' => (string) $vehicle->getId(),
            'originalFilename' => self::faker()->word().'.jpg',
            'storedPath' => self::faker()->uuid().'.jpg',
            'mimeType' => 'image/jpeg',
            'sizeBytes' => self::faker()->numberBetween(1_000, 5_000_000),
            'uploadedBy' => UserFactory::createOne(),
        ];
    }
}
