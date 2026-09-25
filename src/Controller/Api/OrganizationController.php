<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\MemberInviteRequest;
use App\Dto\Request\OrganizationRequest;
use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\OrganizationMemberRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Security\Voter\OrganizationVoter;
use App\Service\AppMailer;
use App\Service\MailBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Organization')]
#[Route('/api/organizations', name: 'api_organizations_')]
#[IsGranted('ROLE_USER')]
final class OrganizationController extends AbstractController
{
    use ProblemDetailsResponseTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly OrganizationRepository $orgRepo,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly OrganizationInvitationRepository $invitationRepo,
        private readonly UserRepository $userRepo,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
        private readonly AppMailer $mailer,
        private readonly MailBuilder $mailBuilder,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[OA\Get(
        summary: 'List organizations the user belongs to',
        responses: [new OA\Response(response: 200, description: 'Array', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: Organization::class, groups: ['org:list']))))],
    )]
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $memberships = $this->memberRepo->findAllForUser($user);
        $orgs = array_map(static fn (OrganizationMember $m) => $m->getOrganization(), $memberships);
        return $this->jsonGroups($orgs, ['org:list']);
    }

    #[OA\Get(
        summary: 'Organization detail',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Organization', content: new OA\JsonContent(ref: new Model(type: Organization::class, groups: ['org:read']))),
            new OA\Response(response: 403, description: 'Not a member'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::VIEW, $org);
        return $this->jsonGroups($org, ['org:read']);
    }

    #[OA\Post(
        summary: 'Create a new organization (user becomes owner)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: OrganizationRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: new Model(type: Organization::class, groups: ['org:read']))),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(#[MapRequestPayload] OrganizationRequest $payload): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $org = (new Organization())
            ->setName($payload->name)
            ->setSlug($payload->slug ?? $this->buildUniqueSlug($payload->name, $user->getId()));

        $errors = $this->validator->validate($org);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $membership = (new OrganizationMember())
            ->setOrganization($org)
            ->setUser($user)
            ->setRole(OrgRole::OWNER)
            ->setAcceptedAt(new \DateTimeImmutable());

        $this->em->persist($org);
        $this->em->persist($membership);
        $this->em->flush();

        return $this->jsonGroups($org, ['org:read'], 201);
    }

    #[OA\Put(
        summary: 'Update organization (owner/admin only)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: OrganizationRequest::class))),
        responses: [
            new OA\Response(response: 200, description: 'Updated', content: new OA\JsonContent(ref: new Model(type: Organization::class, groups: ['org:read']))),
            new OA\Response(response: 403, description: 'No EDIT permission (member role)'),
        ],
    )]
    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, #[MapRequestPayload] OrganizationRequest $payload): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::EDIT, $org);

        $org->setName($payload->name);
        if ($payload->slug !== null && $payload->slug !== '') {
            $org->setSlug($payload->slug);
        }

        $errors = $this->validator->validate($org);
        if (count($errors) > 0) {
            return $this->validationError($errors);
        }

        $this->em->flush();
        return $this->jsonGroups($org, ['org:read']);
    }

    #[OA\Delete(
        summary: 'Delete organization (owner only — cascades to all data)',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Deleted'),
            new OA\Response(response: 403, description: 'Owner only'),
        ],
    )]
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::DELETE, $org);

        // FK ON DELETE CASCADE rimuoverà membri, veicoli, manutenzioni ecc.
        $this->em->remove($org);
        $this->em->flush();
        return new JsonResponse(null, 204);
    }

    // ----- Members -----

    #[OA\Get(
        summary: 'List org members',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Array of memberships', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: OrganizationMember::class, groups: ['membership:read', 'user:list'])))),
        ],
    )]
    #[Route('/{id}/members', name: 'members_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listMembers(int $id): JsonResponse
    {
        $org = $this->mustFind($id);
        // Solo owner/admin vede la lista membri (un membro non gestisce l'org).
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        $members = $this->memberRepo->findBy(['organization' => $org], ['createdAt' => 'ASC']);
        return $this->jsonGroups($members, ['membership:read', 'user:list']);
    }

    #[OA\Get(
        summary: 'List pending invitations for the org',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Array of pending invitations', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: OrganizationInvitation::class, groups: ['invitation:read'])))),
        ],
    )]
    #[Route('/{id}/invitations', name: 'invitations_list', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listInvitations(int $id): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        return $this->jsonGroups($this->invitationRepo->findPendingByOrganization($org), ['invitation:read']);
    }

    /**
     * Invita un indirizzo email all'org (l'invitato può non avere ancora un account:
     * lo creerà all'accettazione). Crea un OrganizationInvitation pendente e manda
     * l'email con token (7 giorni). Un solo invito attivo per (org, email): un nuovo
     * invito invalida i precedenti. 409 solo se l'email è già membro accettato.
     */
    #[OA\Post(
        summary: 'Invite an email address to the org',
        description: 'Owner/admin only. Works for non-registered emails (they sign up on accept). Resends if a pending invite exists. 409 only if already a member.',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MemberInviteRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'Invitation sent', content: new OA\JsonContent(ref: new Model(type: OrganizationInvitation::class, groups: ['invitation:read']))),
            new OA\Response(response: 409, description: 'member.already_exists'),
        ],
    )]
    #[Route('/{id}/members', name: 'members_invite', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function inviteMember(int $id, #[MapRequestPayload] MemberInviteRequest $payload): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        $email = mb_strtolower(trim($payload->email));

        // Se l'email è già di un utente che è già membro → 409.
        $existingUser = $this->userRepo->findOneByEmail($email);
        if ($existingUser !== null && $this->memberRepo->findMembership($existingUser, $org) !== null) {
            return $this->problem('member.already_exists', 409);
        }

        /** @var User $inviter */
        $inviter = $this->getUser();

        // Un solo invito pendente per (org, email): invalida i precedenti (re-invito).
        $this->invitationRepo->invalidateForOrgEmail($org, $email);

        $rawToken = bin2hex(random_bytes(32));
        $invitation = new OrganizationInvitation(
            $org,
            $email,
            $payload->role,
            hash('sha256', $rawToken),
            (new \DateTimeImmutable())->modify('+7 days'),
            $inviter,
        );
        $this->em->persist($invitation);
        $this->em->flush();

        try {
            $this->mailer->send(
                $this->mailBuilder->invitation($email, $org, $inviter, $rawToken, $inviter->getLocale())->to($email),
            );
        } catch (\Throwable $e) {
            $this->logger->error('Invitation email failed', [
                'org_id' => $org->getId(),
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->jsonGroups($invitation, ['invitation:read'], 201);
    }

    #[OA\Delete(
        summary: 'Revoke a pending invitation',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'invId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Revoked'),
            new OA\Response(response: 404, description: 'invitation.not_found'),
        ],
    )]
    #[Route('/{id}/invitations/{invId}', name: 'invitations_revoke', methods: ['DELETE'], requirements: ['id' => '\d+', 'invId' => '\d+'])]
    public function revokeInvitation(int $id, int $invId): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        $invitation = $this->invitationRepo->find($invId);
        if (!$invitation || $invitation->getOrganization()->getId() !== $org->getId()) {
            return $this->problem('invitation.not_found', 404);
        }

        $this->em->remove($invitation);
        $this->em->flush();
        return new JsonResponse(null, 204);
    }

    #[OA\Delete(
        summary: 'Remove member from organization',
        description: 'Owner cannot be removed (transfer ownership first).',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'memberId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Removed'),
            new OA\Response(response: 404, description: 'member.not_found'),
            new OA\Response(response: 409, description: 'member.cannot_remove_owner'),
        ],
    )]
    #[Route('/{id}/members/{memberId}', name: 'members_delete', methods: ['DELETE'], requirements: ['id' => '\d+', 'memberId' => '\d+'])]
    public function removeMember(int $id, int $memberId): JsonResponse
    {
        $org = $this->mustFind($id);
        $this->denyAccessUnlessGranted(OrganizationVoter::MANAGE_MEMBERS, $org);

        $member = $this->memberRepo->find($memberId);
        if (!$member || $member->getOrganization()->getId() !== $org->getId()) {
            return $this->problem('member.not_found', 404);
        }

        if ($member->getRole() === OrgRole::OWNER) {
            return $this->problem('member.cannot_remove_owner', 409);
        }

        $this->em->remove($member);
        $this->em->flush();
        return new JsonResponse(null, 204);
    }

    // ----- helpers -----

    private function mustFind(int $id): Organization
    {
        $org = $this->orgRepo->find($id);
        if (!$org) {
            throw $this->createNotFoundException('org.not_found');
        }
        return $org;
    }

    private function buildUniqueSlug(string $name, int $userId): string
    {
        $base = strtolower((string) $this->slugger->slug($name));
        if ($base === '') {
            $base = 'workspace';
        }
        $candidate = $base;
        $suffix = 1;
        while ($this->orgRepo->findOneBySlug($candidate) !== null) {
            $candidate = $base.'-'.$userId.'-'.$suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function jsonGroups(mixed $data, array $groups, int $status = 200): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($data, 'json', ['groups' => $groups]), $status, [], json: true);
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
