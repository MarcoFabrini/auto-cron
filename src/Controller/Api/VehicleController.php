<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\VehicleRequest;
use App\Dto\Request\VehicleShareRequest;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\ShareRole;
use App\Repository\OrganizationMemberRepository;
use App\Repository\VehicleRepository;
use App\Repository\VehicleShareRepository;
use App\Security\Voter\VehicleVoter;
use App\Service\ActiveOrganizationResolver;
use App\Service\VehicleAccessChecker;
use App\Service\VehicleStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Vehicle')]
#[Route('/api/vehicles', name: 'api_vehicles_')]
#[IsGranted('ROLE_USER')]
final class VehicleController extends AbstractController
{
    use ProblemDetailsResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleRepository $vehicleRepo,
        private readonly VehicleShareRepository $shareRepo,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly ActiveOrganizationResolver $orgResolver,
        private readonly VehicleAccessChecker $access,
        private readonly SerializerInterface $serializer,
        private readonly NormalizerInterface $normalizer,
        private readonly ValidatorInterface $validator,
        private readonly VehicleStatsService $stats,
    ) {
    }

    #[OA\Get(
        summary: 'List accessible vehicles in active organization',
        description: 'Org admin/owner sees all. Plain member sees only vehicles with explicit VehicleShare. Excludes archived.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Array of vehicles',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Vehicle::class, groups: ['vehicle:list']))),
            ),
        ],
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $org = $this->orgResolver->resolve();

        $vehicles = $this->vehicleRepo->findAccessibleByUserInOrganization(
            $user,
            $org,
            includeArchived: false,
            isOrgAdmin: $this->access->isOrgAdmin($user, $org),
        );

        return $this->jsonGroups($vehicles, ['vehicle:list']);
    }

    #[OA\Get(
        summary: 'Vehicle detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Vehicle', content: new OA\JsonContent(ref: new Model(type: Vehicle::class, groups: ['vehicle:read']))),
            new OA\Response(response: 403, description: 'No access'),
            new OA\Response(response: 404, description: 'Not found / cross-tenant'),
        ],
    )]
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        /** @var User $user */
        $user = $this->getUser();
        /** @var array<string, mixed> $data */
        $data = $this->normalizer->normalize($vehicle, 'json', ['groups' => ['vehicle:read']]);
        // Permessi dell'utente corrente su QUESTO veicolo: il frontend li usa per
        // mostrare/nascondere modifica, eliminazione e condivisione (il backend
        // applica comunque i voter). Condividere richiede EDIT, come createShare.
        $canEdit = $this->access->canEdit($user, $vehicle);
        $data['permissions'] = [
            'canEdit' => $canEdit,
            'canDelete' => $this->access->canDelete($user, $vehicle),
            'canShare' => $canEdit,
        ];

        return new JsonResponse($data);
    }

    #[OA\Post(
        summary: 'Create vehicle in active organization',
        description: 'Any accepted org member. A plain member becomes the vehicle owner (admin share). Bi-fuel via secondaryFuelType (must differ from fuelType).',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VehicleRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: new Model(type: Vehicle::class, groups: ['vehicle:read']))),
            new OA\Response(response: 422, description: 'validation_failed (e.g. vehicle.duplicate_fuel_type)'),
        ],
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] VehicleRequest $payload): JsonResponse
    {
        // Ogni membro accettato dell'org attiva può creare veicoli (la membership
        // è già garantita dal resolver).
        $org = $this->orgResolver->resolve();
        /** @var User $user */
        $user = $this->getUser();

        $vehicle = (new Vehicle())
            ->setOrganization($org)
            ->setName($payload->name)
            ->setBrand($payload->brand)
            ->setModel($payload->model)
            ->setYear($payload->year)
            ->setType($payload->type)
            ->setFuelType($payload->fuelType)
            ->setSecondaryFuelType($payload->secondaryFuelType)
            ->setLicensePlate($payload->licensePlate)
            ->setVin($payload->vin)
            ->setInitialKm($payload->initialKm)
            ->setNotes($payload->notes);

        // Valida vincoli a livello entity (es. bi-fuel duplicato)
        $errors = $this->validator->validate($vehicle);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->persist($vehicle);

        // Un member che crea un veicolo ne diventa il proprietario: share `admin`
        // accettato, così lo vede e lo gestisce (modifica, eliminazione,
        // condivisione). Org owner/admin hanno già accesso totale via ruolo org.
        if (!$this->access->isOrgAdmin($user, $org)) {
            $ownerShare = (new VehicleShare())
                ->setVehicle($vehicle)
                ->setUser($user)
                ->setRole(ShareRole::ADMIN)
                ->setInvitedBy($user)
                ->setAcceptedAt(new \DateTimeImmutable());
            $vehicle->getShares()->add($ownerShare);
            $this->em->persist($ownerShare);
        }

        $this->em->flush();

        return $this->jsonGroups($vehicle, ['vehicle:read'], 201);
    }

    #[OA\Put(
        summary: 'Update vehicle',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VehicleRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: new Model(type: Vehicle::class, groups: ['vehicle:read']))),
            new OA\Response(response: 403, description: 'No EDIT permission'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] VehicleRequest $payload): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $vehicle
            ->setName($payload->name)
            ->setBrand($payload->brand)
            ->setModel($payload->model)
            ->setYear($payload->year)
            ->setType($payload->type)
            ->setFuelType($payload->fuelType)
            ->setSecondaryFuelType($payload->secondaryFuelType)
            ->setLicensePlate($payload->licensePlate)
            ->setVin($payload->vin)
            ->setInitialKm($payload->initialKm)
            ->setNotes($payload->notes);

        $errors = $this->validator->validate($vehicle);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->flush();

        return $this->jsonGroups($vehicle, ['vehicle:read']);
    }

    #[OA\Delete(
        summary: 'Delete vehicle (cascades to maintenances, refuelings, expenses, reminders, shares)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'No DELETE permission'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::DELETE, $vehicle);

        $this->em->remove($vehicle);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    /**
     * Statistiche aggregate del veicolo: consumo medio per fuel, totali costi,
     * km percorsi, costo per km. Cache 5 minuti.
     */
    #[OA\Get(
        summary: 'Aggregated vehicle stats (cached 5min)',
        description: 'Per-fuel km/l consumption, total cost, kmDriven, costPerKm.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Stats payload',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'consumption', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'number', nullable: true)),
                    new OA\Property(property: 'totals', type: 'object', properties: [
                        new OA\Property(property: 'cost', type: 'string'),
                        new OA\Property(property: 'refuelings', type: 'integer'),
                        new OA\Property(property: 'maintenances', type: 'integer'),
                        new OA\Property(property: 'expenses', type: 'integer'),
                    ]),
                    new OA\Property(property: 'currentKm', type: 'integer'),
                    new OA\Property(property: 'kmDriven', type: 'integer'),
                    new OA\Property(property: 'costPerKm', type: 'number', nullable: true),
                ]),
            ),
        ],
    )]
    #[Route('/{id}/stats', name: 'stats', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stats(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        return new JsonResponse($this->stats->compute($vehicle));
    }

    #[OA\Post(
        summary: 'Archive vehicle (soft delete — sets archived_at)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Archived')],
    )]
    #[Route('/{id}/archive', name: 'archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archive(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::DELETE, $vehicle);

        $vehicle->setArchivedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->jsonGroups($vehicle, ['vehicle:read']);
    }

    // ----- Share management -----

    #[OA\Get(
        summary: 'List vehicle shares (user-to-user within organization)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Array of shares', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: VehicleShare::class, groups: ['share:read', 'user:list'])))),
        ],
    )]
    #[Route('/{id}/shares', name: 'shares_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listShares(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::VIEW, $vehicle);

        return $this->jsonGroups($this->shareRepo->findByVehicle($vehicle), ['share:read', 'user:list']);
    }

    #[OA\Get(
        summary: 'Org members this vehicle can be shared with (names only, no emails)',
        description: 'Accepted org members with role member, excluding the caller and anyone who already has a share (org owner/admin already see everything). Requires EDIT on the vehicle.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Array of {id, firstName, lastName}, ordered by last then first name',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'firstName', type: 'string'),
                    new OA\Property(property: 'lastName', type: 'string'),
                ], type: 'object')),
            ),
        ],
    )]
    #[Route('/{id}/share-candidates', name: 'share_candidates', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function shareCandidates(int $id): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        /** @var User $user */
        $user = $this->getUser();
        $alreadyShared = $this->sharedUserIds($vehicle);

        $candidates = [];
        foreach ($this->memberRepo->findShareableMembers($vehicle->getOrganization(), $user) as $member) {
            $candidate = $member->getUser();
            if (isset($alreadyShared[(int) $candidate->getId()])) {
                continue;
            }
            $candidates[] = [
                'id' => $candidate->getId(),
                'firstName' => $candidate->getFirstName(),
                'lastName' => $candidate->getLastName(),
            ];
        }

        return new JsonResponse($candidates);
    }

    #[OA\Post(
        summary: 'Share vehicle read-only with one or more org members (auto-accepted)',
        description: 'Shares are always viewer (read-only). Requires EDIT on the vehicle. Targets are user ids taken from share-candidates; anything else (unknown id, outsider, org owner/admin, yourself) gets the same 422, so it cannot be used to discover accounts. Already-shared ids are ignored (idempotent). The request is atomic: one invalid id rejects all.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: VehicleShareRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Array of the shares actually created (may be empty)'),
            new OA\Response(response: 422, description: 'share.not_org_member | validation_failed'),
        ],
    )]
    #[Route('/{id}/shares', name: 'shares_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createShare(int $id, #[MapRequestPayload] VehicleShareRequest $payload): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        /** @var User $inviter */
        $inviter = $this->getUser();

        // Solo membri condivisibili (accettati, ruolo member, non chi condivide):
        // verso chiunque altro lo share sarebbe inerte o senza senso. Stessa
        // risposta per id inesistente e per id di un estraneo: nessuna enumerazione.
        $shareable = [];
        foreach ($this->memberRepo->findShareableMembers($vehicle->getOrganization(), $inviter) as $member) {
            $shareable[(int) $member->getUser()->getId()] = $member->getUser();
        }

        $targets = [];
        foreach (array_unique($payload->userIds) as $userId) {
            if (!isset($shareable[$userId])) {
                return $this->problem('share.not_org_member', 422);
            }
            $targets[] = $shareable[$userId];
        }

        $alreadyShared = $this->sharedUserIds($vehicle);
        $created = [];
        foreach ($targets as $target) {
            if (isset($alreadyShared[(int) $target->getId()])) {
                continue;
            }
            $share = (new VehicleShare())
                ->setVehicle($vehicle)
                ->setUser($target)
                // Le condivisioni sono sempre in sola lettura: modifica e gestione
                // restano al proprietario del veicolo e agli org owner/admin.
                ->setRole(ShareRole::VIEWER)
                ->setInvitedBy($inviter)
                ->setAcceptedAt(new \DateTimeImmutable()); // auto-accept per ora; inviti veri in fase futura
            $this->em->persist($share);
            $created[] = $share;
        }
        $this->em->flush();

        return $this->jsonGroups($created, ['share:read', 'user:list'], 201);
    }

    #[OA\Delete(
        summary: 'Revoke vehicle share',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'shareId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Revoked'),
            new OA\Response(response: 409, description: 'share.cannot_remove_owner'),
        ],
    )]
    #[Route('/{id}/shares/{shareId}', name: 'shares_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'shareId' => '\d+'])]
    public function deleteShare(int $id, int $shareId): JsonResponse
    {
        $vehicle = $this->mustFind($id);
        $this->denyAccessUnlessGranted(VehicleVoter::EDIT, $vehicle);

        $share = $this->shareRepo->find($shareId);
        if (!$share || $share->getVehicle()->getId() !== $vehicle->getId()) {
            return $this->problem('share.not_found', 404);
        }

        // Lo share `admin` è la proprietà del veicolo (chi l'ha creato): revocarlo
        // lascerebbe il proprietario fuori dal proprio veicolo.
        if ($share->getRole() === ShareRole::ADMIN) {
            return $this->problem('share.cannot_remove_owner', 409);
        }

        $this->em->remove($share);
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    // ----- helpers -----

    /**
     * @return array<int, true> id degli utenti che hanno già uno share sul veicolo
     */
    private function sharedUserIds(Vehicle $vehicle): array
    {
        $ids = [];
        foreach ($this->shareRepo->findByVehicle($vehicle) as $share) {
            $ids[(int) $share->getUser()->getId()] = true;
        }

        return $ids;
    }

    private function mustFind(int $id): Vehicle
    {
        $org = $this->orgResolver->resolve();
        $vehicle = $this->vehicleRepo->findOneInOrganization($id, $org);
        if (!$vehicle) {
            throw $this->createNotFoundException('vehicle.not_found');
        }
        return $vehicle;
    }

    private function jsonGroups(mixed $data, array $groups, int $status = 200): JsonResponse
    {
        return new JsonResponse(
            $this->serializer->serialize($data, 'json', ['groups' => $groups]),
            $status,
            [],
            json: true,
        );
    }

    private function validationError(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $details = [];
        foreach ($errors as $error) {
            $details[] = ['field' => $error->getPropertyPath(), 'message' => $error->getMessage()];
        }
        return new JsonResponse(
            ['type' => 'about:blank', 'title' => 'validation_failed', 'status' => 422, 'errors' => $details],
            422,
        );
    }
}
