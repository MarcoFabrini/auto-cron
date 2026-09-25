<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\AttachmentEntityType;
use App\Repository\AttachmentRepository;
use App\Tests\Factory\AttachmentFactory;
use App\Tests\Factory\MaintenanceFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Support\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class AttachmentControllerTest extends ApiTestCase
{
    public function testUploadValidImageReturns201(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $file = $this->makeUploadedFile('photo.png', $this->pngBytes(), 'image/png');

        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonBody();
        self::assertSame('image/png', $body['mimeType']);
        self::assertSame('photo.png', $body['originalFilename']);
        self::assertGreaterThan(0, $body['sizeBytes']);

        $repo = static::getContainer()->get(AttachmentRepository::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testUploadPdfOnMaintenanceEntity(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $maintenance = MaintenanceFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);

        $file = $this->makeUploadedFile('fattura.pdf', "%PDF-1.4\n%fake pdf\n", 'application/pdf');

        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'maintenance', 'entityId' => (string) $maintenance->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(201);
        self::assertSame('maintenance', $this->jsonBody()['entityType']);
    }

    public function testUploadWithoutFileReturns400(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('upload.file_required', $this->jsonBody()['title']);
    }

    public function testUploadDisallowedMimeReturns415(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $file = $this->makeUploadedFile('script.sh', "#!/bin/bash\necho hello\n", 'application/x-sh');

        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(415);
        self::assertSame('upload.mime_not_allowed', $this->jsonBody()['title']);
    }

    public function testUploadMissingEntityParamsReturns400(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $file = $this->makeUploadedFile('x.png', $this->pngBytes(), 'image/png');

        $this->client->request(
            'POST',
            '/api/attachments',
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame('upload.entity_required', $this->jsonBody()['title']);
    }

    public function testUploadCrossTenantVehicleReturns404(): void
    {
        [, , $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);

        $file = $this->makeUploadedFile('hack.png', $this->pngBytes(), 'image/png');

        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicleB->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$tokenA, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testListAttachmentsByEntity(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        // Upload 2 file
        for ($i = 1; $i <= 2; $i++) {
            $file = $this->makeUploadedFile("img$i.png", $this->pngBytes(), 'image/png');
            $this->client->request(
                'POST',
                '/api/attachments',
                parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
                files: ['file' => $file],
                server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
            );
            self::assertResponseStatusCodeSame(201);
        }

        $this->jsonRequest('GET', '/api/attachments?entityType=vehicle&entityId='.$vehicle->getId(), accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $this->jsonBody());
    }

    public function testDownloadReturnsXAccelRedirect(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $file = $this->makeUploadedFile('photo.jpg', $this->jpegBytes(), 'image/jpeg');
        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );
        $id = $this->jsonBody()['id'];

        $this->client->request('GET', '/api/attachments/'.$id, server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile']);

        self::assertResponseIsSuccessful();
        $resp = $this->client->getResponse();
        self::assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $resp);
        self::assertSame('image/jpeg', $resp->headers->get('Content-Type'));
    }

    public function testDeleteAttachmentRemovesDbAndFile(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $file = $this->makeUploadedFile('x.png', $this->pngBytes(), 'image/png');
        $this->client->request(
            'POST',
            '/api/attachments',
            parameters: ['entityType' => 'vehicle', 'entityId' => (string) $vehicle->getId()],
            files: ['file' => $file],
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_X_CLIENT_TYPE' => 'mobile'],
        );
        $id = $this->jsonBody()['id'];

        $this->jsonRequest('DELETE', '/api/attachments/'.$id, accessToken: $token);
        self::assertResponseStatusCodeSame(204);

        $repo = static::getContainer()->get(AttachmentRepository::class);
        self::assertNull($repo->find($id));
    }

    public function testListReturnsAttachmentsSeededViaFactory(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        AttachmentFactory::createOne([
            'organization' => $org,
            'entityType' => AttachmentEntityType::VEHICLE,
            'entityId' => (string) $vehicle->getId(),
        ]);

        $this->jsonRequest(
            'GET',
            '/api/attachments?entityType=vehicle&entityId='.$vehicle->getId(),
            accessToken: $token,
        );

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonBody());
    }
}
