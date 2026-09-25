<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AuditLog;
use App\Entity\Expense;
use App\Entity\Maintenance;
use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\Refueling;
use App\Entity\Reminder;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\AuditAction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Audit log subscriber (#3.6) — append-only.
 *
 * onFlush: ispeziona UnitOfWork (insertions/updates/deletions) e raccoglie le
 * change-set degli entity tracciati. Persiste i log nel postFlush per evitare
 * di alterare il flush corrente.
 *
 * Entità tracciate hardcoded (no listener globale che spammerebbe): Vehicle,
 * Maintenance, Refueling, Expense, Reminder, VehicleShare, OrganizationMember,
 * Organization.
 *
 * Esclude PII updates su User (gestito via GDPR), token auth, push subs.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    /**
     * @var list<array{
     *   entity: object|null,
     *   entity_class: string,
     *   entity_id: string|null,
     *   action: AuditAction,
     *   changes: array<string, array{0: mixed, 1: mixed}>|null,
     *   organization_id: int|null,
     * }>
     */
    private array $pending = [];

    /**
     * Org ID che vengono cancellati nello stesso flush — i log che vi puntano
     * devono usare organization_id NULL (FK violation altrimenti).
     *
     * @var array<int, true>
     */
    private array $deletedOrgIds = [];

    /** @var list<class-string> */
    private const TRACKED = [
        Vehicle::class,
        Maintenance::class,
        Refueling::class,
        Expense::class,
        Reminder::class,
        VehicleShare::class,
        OrganizationMember::class,
        Organization::class,
    ];

    /** Campi da escludere dal diff (rumore o sensibili) */
    private const IGNORED_FIELDS = ['updatedAt', 'lastNotifiedAt', 'lastSeenAt', 'password'];

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // Pre-pass: collect IDs of orgs being deleted so child logs use NULL org_id
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof Organization) {
                $id = $entity->getId();
                if (is_int($id)) {
                    $this->deletedOrgIds[$id] = true;
                }
            }
        }

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($this->isTracked($entity)) {
                // Defer ID extraction to postFlush — auto-increment IDs assigned post-INSERT
                $this->capture($entity, AuditAction::CREATED, null, deferId: true);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->isTracked($entity)) {
                continue;
            }
            $changeSet = $uow->getEntityChangeSet($entity);
            $diff = $this->buildDiff($changeSet);
            if ($diff === []) {
                continue;
            }
            $this->capture($entity, AuditAction::UPDATED, $diff);
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($this->isTracked($entity)) {
                $this->capture($entity, AuditAction::DELETED, null);
            }
        }
    }

    /**
     * @param array<string, array{0: mixed, 1: mixed}>|null $diff
     */
    private function capture(object $entity, AuditAction $action, ?array $diff, bool $deferId = false): void
    {
        $org = $this->resolveOrganization($entity);
        $orgId = null;
        if ($org instanceof Organization) {
            $id = $org->getId();
            if (is_int($id) && !isset($this->deletedOrgIds[$id])) {
                $orgId = $id;
            }
        }

        $this->pending[] = [
            'entity' => $deferId ? $entity : null,
            'entity_class' => $this->shortName($entity),
            'entity_id' => $deferId ? null : (string) ($this->extractId($entity) ?? '?'),
            'action' => $action,
            'changes' => $diff,
            'organization_id' => $orgId,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->pending === []) {
            return;
        }

        $em = $args->getObjectManager();
        $pending = $this->pending;
        $this->pending = [];

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp();
        $ua = $request?->headers->get('User-Agent');
        $user = $this->security->getUser();
        $userId = $user instanceof User ? $user->getId() : null;

        // INSERT via raw SQL — evita ricomparsa di entità removed nell'identity map
        $conn = $em->getConnection();
        foreach ($pending as $row) {
            $entityId = $row['entity_id'];
            if ($entityId === null && $row['entity'] !== null) {
                // ID assegnato dopo INSERT (auto-increment)
                $entityId = (string) ($this->extractId($row['entity']) ?? '?');
            }

            $conn->insert('audit_logs', [
                'entity_class' => $row['entity_class'],
                'entity_id' => $entityId ?? '?',
                'action' => $row['action']->value,
                'changes' => $row['changes'] !== null ? json_encode($row['changes'], \JSON_THROW_ON_ERROR) : null,
                'organization_id' => $row['organization_id'],
                'user_id' => $userId,
                'ip_address' => $ip,
                'user_agent' => $ua !== null ? substr($ua, 0, 500) : null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $this->deletedOrgIds = [];
    }

    private function isTracked(object $entity): bool
    {
        foreach (self::TRACKED as $cls) {
            if ($entity instanceof $cls) {
                return true;
            }
        }
        return false;
    }

    private function shortName(object $entity): string
    {
        $cls = $entity::class;
        $pos = strrpos($cls, '\\');
        return $pos === false ? $cls : substr($cls, $pos + 1);
    }

    private function extractId(object $entity): int|string|null
    {
        if (method_exists($entity, 'getId')) {
            $id = $entity->getId();
            return is_int($id) || is_string($id) ? $id : null;
        }
        return null;
    }

    private function resolveOrganization(object $entity): ?Organization
    {
        if (method_exists($entity, 'getOrganization')) {
            $org = $entity->getOrganization();
            if ($org instanceof Organization) {
                return $org;
            }
        }
        if ($entity instanceof Organization) {
            return $entity;
        }
        return null;
    }

    /**
     * Il change set Doctrine può contenere PersistentCollection per le
     * relazioni to-many: non sono diff campo→[old,new], le saltiamo.
     *
     * @param array<string, array{0: mixed, 1: mixed}|\Doctrine\ORM\PersistentCollection<array-key, object>> $changeSet
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function buildDiff(array $changeSet): array
    {
        $diff = [];
        foreach ($changeSet as $field => $change) {
            if (!is_array($change) || in_array($field, self::IGNORED_FIELDS, true)) {
                continue;
            }
            [$old, $new] = $change;
            $diff[$field] = [$this->normalize($old), $this->normalize($new)];
        }
        return $diff;
    }

    private function normalize(mixed $v): mixed
    {
        if ($v instanceof \BackedEnum) {
            return $v->value;
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format(\DATE_ATOM);
        }
        if (is_object($v)) {
            if (method_exists($v, 'getId')) {
                return ['_ref' => $this->shortName($v), 'id' => $v->getId()];
            }
            return ['_class' => $v::class];
        }
        return $v;
    }
}
