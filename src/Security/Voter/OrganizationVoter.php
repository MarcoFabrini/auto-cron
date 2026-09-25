<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Repository\OrganizationMemberRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Organization>
 */
final class OrganizationVoter extends Voter
{
    public const VIEW = 'ORG_VIEW';
    public const EDIT = 'ORG_EDIT';
    public const MANAGE_MEMBERS = 'ORG_MANAGE_MEMBERS';
    public const DELETE = 'ORG_DELETE';

    public function __construct(
        private readonly OrganizationMemberRepository $memberRepo,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Organization
            && in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE_MEMBERS, self::DELETE], true);
    }

    /**
     * @param Organization $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->memberRepo->findMembership($user, $subject);
        if (!$membership || !$membership->isAccepted()) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::MANAGE_MEMBERS => $membership->getRole()->canManageMembers(),
            self::DELETE => $membership->getRole()->canManageOrg(),
            default => false,
        };
    }
}
