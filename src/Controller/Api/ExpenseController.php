<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\ExpenseRequest;
use App\Entity\Expense;
use App\Repository\ExpenseRepository;
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

#[OA\Tag(name: 'Expense')]
#[Route('/api/expenses', name: 'api_expenses_')]
#[IsGranted('ROLE_USER')]
final class ExpenseController extends AbstractController
{
    use ProblemDetailsResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExpenseRepository $repo,
        private readonly VehicleRepository $vehicleRepo,
        private readonly ActiveOrganizationResolver $orgResolver,
        private readonly SerializerInterface $serializer,
    ) {
    }

    #[OA\Get(
        summary: 'List expenses for a vehicle (paginated)',
        parameters: [
            new OA\Parameter(name: 'vehicleId', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Array', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Expense::class, groups: ['expense:list', 'vehicle:nested'])))),
        ],
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

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        return $this->jsonGroups($this->repo->findByVehiclePaginated($vehicle, $page, $limit), ['expense:list', 'vehicle:nested']);
    }

    #[OA\Get(
        summary: 'Expense detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Expense', content: new OA\JsonContent(ref: new Model(type: Expense::class, groups: ['expense:read', 'vehicle:nested'])))],
    )]
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $e = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $e);
        return $this->jsonGroups($e, ['expense:read', 'vehicle:nested']);
    }

    #[OA\Post(
        summary: 'Create expense entry',
        description: 'Recurring expense uses recurringPeriod (weekly/monthly/quarterly/semiannual/yearly/biennial).',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ExpenseRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: new Model(type: Expense::class, groups: ['expense:read', 'vehicle:nested']))),
            new OA\Response(response: 422, description: 'validation_failed (e.g. validation.amount_format)'),
        ],
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] ExpenseRequest $payload): JsonResponse
    {
        $vehicle = $this->vehicleRepo->findOneInOrganization($payload->vehicleId, $this->orgResolver->resolve());
        if (!$vehicle) {
            throw $this->createNotFoundException('vehicle.not_found');
        }
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $e = (new Expense())
            ->setOrganization($vehicle->getOrganization())
            ->setVehicle($vehicle)
            ->setOccurredAt(new \DateTimeImmutable($payload->occurredAt))
            ->setCategory($payload->category)
            ->setDescription($payload->description)
            ->setAmount($payload->amount)
            ->setRecurring($payload->recurring)
            ->setRecurringPeriod($payload->recurringPeriod)
            ->setNotes($payload->notes);

        $this->em->persist($e);
        $this->em->flush();
        return $this->jsonGroups($e, ['expense:read', 'vehicle:nested'], 201);
    }

    #[OA\Put(
        summary: 'Update expense',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ExpenseRequest::class))),
        responses: [new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: new Model(type: Expense::class, groups: ['expense:read', 'vehicle:nested'])))],
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] ExpenseRequest $payload): JsonResponse
    {
        $e = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $e);

        $e
            ->setOccurredAt(new \DateTimeImmutable($payload->occurredAt))
            ->setCategory($payload->category)
            ->setDescription($payload->description)
            ->setAmount($payload->amount)
            ->setRecurring($payload->recurring)
            ->setRecurringPeriod($payload->recurringPeriod)
            ->setNotes($payload->notes);

        $this->em->flush();
        return $this->jsonGroups($e, ['expense:read', 'vehicle:nested']);
    }

    #[OA\Delete(
        summary: 'Delete expense',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')],
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $e = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $e);
        $this->em->remove($e);
        $this->em->flush();
        return new JsonResponse(null, 204);
    }

    private function mustFind(int $id): Expense
    {
        $e = $this->repo->findOneInOrganization($id, $this->orgResolver->resolve());
        if (!$e) {
            throw $this->createNotFoundException('expense.not_found');
        }
        return $e;
    }

    private function jsonGroups(mixed $data, array $groups, int $status = 200): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($data, 'json', ['groups' => $groups]), $status, [], json: true);
    }
}
