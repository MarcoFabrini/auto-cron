<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Enum\OrgRole;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test del comando CLI `app:gdpr:export` — la logica di export vera è in
 * GdprExportService (già testata a parte); qui si copre il wiring CLI:
 * argomento email, output su stdout vs --output file, --pretty, exit code.
 */
final class GdprExportCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());
        return new CommandTester($application->find('app:gdpr:export'));
    }

    private function createUser(string $email): void
    {
        $user = UserFactory::createOne(['email' => $email]);
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);
    }

    public function testFailsWhenUserNotFound(): void
    {
        $tester = $this->tester();
        $tester->execute(['email' => 'nobody@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testWritesValidJsonToStdoutByDefault(): void
    {
        $this->createUser('export-stdout@test.it');

        $tester = $this->tester();
        $tester->execute(['email' => 'export-stdout@test.it']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $data = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('export-stdout@test.it', $data['user']['email']);
        self::assertSame('Art. 20 — Right to data portability', $data['export_meta']['gdpr_article']);
    }

    public function testWritesToOutputFileWhenGiven(): void
    {
        $this->createUser('export-file@test.it');
        $path = sys_get_temp_dir().'/gdpr-export-test-'.bin2hex(random_bytes(4)).'/user.json';

        $tester = $this->tester();
        $tester->execute(['email' => 'export-file@test.it', '--output' => $path]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('written to', $tester->getDisplay());
        self::assertFileExists($path, 'Deve creare anche le directory intermedie mancanti');

        $data = json_decode((string) file_get_contents($path), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('export-file@test.it', $data['user']['email']);

        unlink($path);
        rmdir(dirname($path));
    }

    public function testOutputFileIsPrivateToTheOwner(): void
    {
        $this->createUser('export-mode@test.it');
        $dir = sys_get_temp_dir().'/gdpr-export-test-'.bin2hex(random_bytes(4));
        $path = $dir.'/nested/user.json';

        $tester = $this->tester();
        $tester->execute(['email' => 'export-mode@test.it', '--output' => $path]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(0o600, fileperms($path) & 0o777, 'Il file con dati personali non deve essere leggibile da altri');
        self::assertSame(0, fileperms(dirname($path)) & 0o077, 'Le directory create non devono essere accessibili a gruppo/altri');

        unlink($path);
        rmdir(dirname($path));
        rmdir($dir);
    }

    public function testExistingLooseFileIsTightenedToPrivate(): void
    {
        $this->createUser('export-tighten@test.it');
        $dir = sys_get_temp_dir().'/gdpr-export-test-'.bin2hex(random_bytes(4));
        mkdir($dir, 0o700);
        $path = $dir.'/user.json';
        file_put_contents($path, 'old');
        chmod($path, 0o644);

        $tester = $this->tester();
        $tester->execute(['email' => 'export-tighten@test.it', '--output' => $path]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(0o600, fileperms($path) & 0o777);

        unlink($path);
        rmdir($dir);
    }

    public function testFailsWithNonZeroExitWhenFileCannotBeWritten(): void
    {
        $this->createUser('export-fail@test.it');
        // Il percorso di destinazione è una directory: file_put_contents non può scriverci
        $dir = sys_get_temp_dir().'/gdpr-export-test-'.bin2hex(random_bytes(4));
        mkdir($dir, 0o700);

        $tester = $this->tester();
        $tester->execute(['email' => 'export-fail@test.it', '--output' => $dir]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Cannot write export', $tester->getDisplay());
        self::assertStringNotContainsString('written to', $tester->getDisplay());

        rmdir($dir);
    }

    public function testPrettyFlagIndentsJson(): void
    {
        $this->createUser('export-pretty@test.it');

        $tester = $this->tester();
        $tester->execute(['email' => 'export-pretty@test.it', '--pretty' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString("\n    ", $tester->getDisplay(), 'JSON_PRETTY_PRINT indenta con 4 spazi');
    }
}
