<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\ReminderRequest;
use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use App\Repository\VehicleRepository;
use App\Security\Voter\VehicleVoter;
use App\Service\ActiveOrganizationResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

#[OA\Tag(name: 'Reminder')]
#[Route('/api/reminders', name: 'api_reminders_')]
#[IsGranted('ROLE_USER')]
final class ReminderController extends AbstractController
{
    use ProblemDetailsResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReminderRepository $repo,
        private readonly VehicleRepository $vehicleRepo,
        private readonly ActiveOrganizationResolver $orgResolver,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[OA\Get(
        summary: 'List reminders for a vehicle',
        parameters: [
            new OA\Parameter(name: 'vehicleId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'onlyActive', in: 'query', schema: new OA\Schema(type: 'boolean', default: true)),
        ],
        responses: [new OA\Response(response: 200, description: 'Array', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Reminder::class, groups: ['reminder:list', 'vehicle:nested']))))],
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $vehicleId = (int) $request->query->get('vehicleId', 0);
        if ($vehicleId <= 0) {
            return $this->problem('query.vehicle_id_required', 400);
        }
        $vehicle = $this->vehicleRepo->findOneInOrganization($vehicleId, $this->orgResolver->resolve());
        if (!$vehicle) {
            throw $this->createNotFoundException('vehicle.not_found');
        }
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        $onlyActive = filter_var($request->query->get('onlyActive', 'true'), FILTER_VALIDATE_BOOLEAN);
        return $this->jsonGroups($this->repo->findByVehicle($vehicle, $onlyActive), ['reminder:list', 'vehicle:nested']);
    }

    #[OA\Get(
        summary: 'Upcoming date-based reminders across all vehicles of the active organization',
        parameters: [
            new OA\Parameter(name: 'days', in: 'query', schema: new OA\Schema(type: 'integer', default: 30)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 10)),
        ],
        responses: [new OA\Response(response: 200, description: 'Array', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Reminder::class, groups: ['reminder:list', 'vehicle:nested']))))],
    )]
    #[Route('/upcoming', name: 'upcoming', methods: ['GET'])]
    public function upcoming(Request $request): JsonResponse
    {
        $days = max(1, min(365, (int) $request->query->get('days', 30)));
        $limit = max(1, min(50, (int) $request->query->get('limit', 10)));

        $reminders = $this->repo->findUpcomingForOrganization($this->orgResolver->resolve(), $days, $limit);
        return $this->jsonGroups($reminders, ['reminder:list', 'vehicle:nested']);
    }

    #[OA\Get(
        summary: 'Reminder detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Reminder', content: new OA\JsonContent(ref: new Model(type: Reminder::class, groups: ['reminder:read', 'vehicle:nested'])))],
    )]
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $r = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $r);
        return $this->jsonGroups($r, ['reminder:read', 'vehicle:nested']);
    }

    #[OA\Post(
        summary: 'Create reminder (date-based or km-based)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ReminderRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: new Model(type: Reminder::class, groups: ['reminder:read', 'vehicle:nested']))),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ReminderRequest $payload): JsonResponse
    {
        $vehicle = $this->vehicleRepo->findOneInOrganization($payload->vehicleId, $this->orgResolver->resolve());
        if (!$vehicle) {
            throw $this->createNotFoundException('vehicle.not_found');
        }
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $r = (new Reminder())
            ->setOrganization($vehicle->getOrganization())
            ->setVehicle($vehicle)
            ->setType($payload->type)
            ->setDescription($payload->description)
            ->setDueDate($payload->dueDate ? new \DateTimeImmutable($payload->dueDate) : null)
            ->setDueKm($payload->dueKm)
            ->setNotifyDaysBefore($payload->notifyDaysBefore);

        $this->em->persist($r);
        $this->em->flush();
        return $this->jsonGroups($r, ['reminder:read', 'vehicle:nested'], 201);
    }

    #[OA\Put(
        summary: 'Update reminder',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ReminderRequest::class))),
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: new Model(type: Reminder::class, groups: ['reminder:read', 'vehicle:nested'])))],
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] ReminderRequest $payload): JsonResponse
    {
        $r = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $r);

        $r
            ->setType($payload->type)
            ->setDescription($payload->description)
            ->setDueDate($payload->dueDate ? new \DateTimeImmutable($payload->dueDate) : null)
            ->setDueKm($payload->dueKm)
            ->setNotifyDaysBefore($payload->notifyDaysBefore);

        $this->em->flush();
        return $this->jsonGroups($r, ['reminder:read', 'vehicle:nested']);
    }

    #[OA\Post(
        summary: 'Mark reminder as completed (sets completed_at)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Completed', content: new OA\JsonContent(ref: new Model(type: Reminder::class, groups: ['reminder:read', 'vehicle:nested'])))],
    )]
    #[Route('/{id}/complete', name: 'complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function complete(int $id): JsonResponse
    {
        $r = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $r);
        $r->setCompletedAt(new \DateTimeImmutable());
        $this->em->flush();
        return $this->jsonGroups($r, ['reminder:read', 'vehicle:nested']);
    }

    #[OA\Delete(
        summary: 'Delete reminder',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')],
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $r = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $r);
        $this->em->remove($r);
        $this->em->flush();
        return new JsonResponse(null, 204);
    }

    private function mustFind(int $id): Reminder
    {
        $r = $this->repo->findOneInOrganization($id, $this->orgResolver->resolve());
        if (!$r) {
            throw $this->createNotFoundException('reminder.not_found');
        }
        return $r;
    }

    private function jsonGroups(mixed $data, array $groups, int $status = 200): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($data, 'json', ['groups' => $groups]), $status, [], json: true);
    }
}
