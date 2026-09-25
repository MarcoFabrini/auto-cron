<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Concern\VehicleScoped;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Service\VehicleAccessChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter usato sia per `Vehicle` sia per qualsiasi entity che implementa
 * `VehicleScoped` (Maintenance, Refueling, Expense, Reminder).
 *
 * @extends Voter<string, Vehicle|VehicleScoped>
 */
final class VehicleVoter extends Voter
{
    public const VIEW = 'VEHICLE_VIEW';
    public const EDIT = 'VEHICLE_EDIT';
    public const DELETE = 'VEHICLE_DELETE';

    public function __construct(private readonly VehicleAccessChecker $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)) {
            return false;
        }
        return $subject instanceof Vehicle || $subject instanceof VehicleScoped;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $vehicle = $subject instanceof Vehicle ? $subject : $subject->getVehicle();

        return match ($attribute) {
            self::VIEW => $this->access->canView($user, $vehicle),
            self::EDIT => $this->access->canEdit($user, $vehicle),
            self::DELETE => $this->access->canDelete($user, $vehicle),
            default => false,
        };
    }
}
