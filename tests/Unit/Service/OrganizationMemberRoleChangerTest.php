<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\OrgRole;
use App\Service\MemberRoleChangeException;
use App\Service\OrganizationMemberRoleChanger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La matrice dei permessi del cambio di ruolo (pura): ogni combinazione attore x bersaglio x nuovo ruolo,
 * per sé e per altri. L'ultimo owner e il no-op non sono permessi e stanno nel test funzionale.
 */
final class OrganizationMemberRoleChangerTest extends TestCase
{
    /** @return iterable<string, array{OrgRole, OrgRole, OrgRole, bool, ?string}> */
    public static function matrix(): iterable
    {
        $roles = [OrgRole::OWNER, OrgRole::ADMIN, OrgRole::MEMBER];
        foreach ($roles as $target) {
            foreach ($roles as $new) {
                foreach ([true, false] as $self) {
                    $who = $self ? 'se stesso' : 'un altro';
                    // OWNER: potere assoluto, anche su di sé (l'ultimo owner lo ferma il service)
                    yield "owner su {$who} {$target->value} -> {$new->value}" => [OrgRole::OWNER, $target, $new, $self, null];
                    // MEMBER: niente
                    yield "member su {$who} {$target->value} -> {$new->value}" => [OrgRole::MEMBER, $target, $new, $self, MemberRoleChangeException::FORBIDDEN];
                    // ADMIN su di sé: sempre rifiutato, qualunque cosa chieda
                    if ($self) {
                        yield "admin su se stesso {$target->value} -> {$new->value}" => [OrgRole::ADMIN, $target, $new, true, MemberRoleChangeException::CANNOT_CHANGE_OWN_ROLE];
                    }
                }
            }
        }
        foreach ($roles as $new) {
            yield "admin su un altro owner -> {$new->value}" => [OrgRole::ADMIN, OrgRole::OWNER, $new, false, MemberRoleChangeException::CANNOT_CHANGE_OWNER_ROLE];
            yield "admin su un altro admin -> {$new->value}" => [OrgRole::ADMIN, OrgRole::ADMIN, $new, false, MemberRoleChangeException::CANNOT_CHANGE_ADMIN_ROLE];
        }
        yield 'admin su un member -> owner' => [OrgRole::ADMIN, OrgRole::MEMBER, OrgRole::OWNER, false, MemberRoleChangeException::OWNER_ROLE_FORBIDDEN];
        yield 'admin su un member -> admin' => [OrgRole::ADMIN, OrgRole::MEMBER, OrgRole::ADMIN, false, null];
        yield 'admin su un member -> member' => [OrgRole::ADMIN, OrgRole::MEMBER, OrgRole::MEMBER, false, null];
    }

    #[DataProvider('matrix')]
    public function testDenialKey(OrgRole $actor, OrgRole $target, OrgRole $new, bool $self, ?string $expected): void
    {
        self::assertSame($expected, OrganizationMemberRoleChanger::denialKey($actor, $target, $new, $self));
    }

    public function testAdminCanOnlyEverPromoteAMemberToAdmin(): void
    {
        $allowed = [];
        foreach (OrgRole::cases() as $target) {
            foreach (OrgRole::cases() as $new) {
                if (OrganizationMemberRoleChanger::denialKey(OrgRole::ADMIN, $target, $new, false) === null) {
                    $allowed[] = $target->value.'>'.$new->value;
                }
            }
        }

        // member>member è il no-op, mai un declassamento
        self::assertSame(['member>admin', 'member>member'], $allowed);
    }

    public function testEveryRejectionKeyIsTranslatedInTheFrontend(): void
    {
        $keys = array_filter(
            (new \ReflectionClass(MemberRoleChangeException::class))->getConstants(),
            static fn (mixed $v, string $name): bool => \is_string($v) && $name !== 'FORBIDDEN',
            \ARRAY_FILTER_USE_BOTH,
        );
        self::assertNotEmpty($keys);

        foreach (['it', 'en'] as $locale) {
            $data = json_decode((string) file_get_contents(\dirname(__DIR__, 3).'/frontend/src/i18n/locales/'.$locale.'.json'), true, flags: \JSON_THROW_ON_ERROR);
            self::assertIsArray($data);
            foreach ($keys as $key) {
                [$domain, $code] = explode('.', (string) $key, 2);
                self::assertIsString($data['errors'][$domain][$code] ?? null, "errors.$key manca in $locale.json");
            }
        }
        // `http.403` è il title del 403 del voter, già tradotto
        self::assertSame('http.403', MemberRoleChangeException::FORBIDDEN);
    }
}
