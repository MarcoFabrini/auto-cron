<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ShareRole;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test: nessun container, nessun DB. Verifica solo la logica dell'enum.
 */
final class ShareRoleTest extends TestCase
{
    /** @return iterable<string, array{ShareRole, bool, bool}> */
    public static function rolePermissions(): iterable
    {
        // [role,           canEdit, canDelete]
        yield 'admin can edit and delete' => [ShareRole::ADMIN, true, true];
        yield 'editor can edit but not delete' => [ShareRole::EDITOR, true, false];
        yield 'viewer can neither' => [ShareRole::VIEWER, false, false];
    }

    #[DataProvider('rolePermissions')]
    public function testRolePermissions(ShareRole $role, bool $canEdit, bool $canDelete): void
    {
        self::assertSame($canEdit, $role->canEdit());
        self::assertSame($canDelete, $role->canDelete());
    }
}
