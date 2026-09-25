<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Command\BackupCreateCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Copre la regressione di sicurezza del backup: con `mariadb-dump | gzip` l'exit
 * code della pipe era quello di gzip (quasi mai fallisce), quindi un dump fallito
 * (host irraggiungibile, credenziali sbagliate) scriveva un .sql.gz vuoto/troncato
 * e il comando riportava comunque successo — la retention avrebbe poi eliminato
 * anche gli ultimi backup buoni. Il dump ora gira senza shell (né pipefail, che
 * dash non supporta): il test esegue mariadb-dump per davvero, come in CI.
 */
final class BackupCreateCommandTest extends KernelTestCase
{
    private string $backupsDir;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->backupsDir = (string) static::getContainer()->getParameter('app.backups_dir');
        (new Filesystem())->remove(glob($this->backupsDir.'/*') ?: []);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(glob($this->backupsDir.'/*') ?: []);
        parent::tearDown();
    }

    public function testCreatesNonEmptyDumpOnSuccess(): void
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:backup:create'));

        $tester->execute(['--skip-uploads' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $dumps = glob($this->backupsDir.'/db-*.sql.gz') ?: [];
        self::assertCount(1, $dumps, 'Deve creare esattamente un file di dump');

        // gzip valido con SQL vero dentro (non un file vuoto/troncato/doppiamente
        // compresso): decomprimendolo si ritrova lo schema dell'app.
        $sql = gzdecode((string) file_get_contents($dumps[0]));
        self::assertIsString($sql, 'Il file deve essere un gzip valido');
        self::assertStringContainsString('CREATE TABLE `users`', $sql);
    }

    public function testFailsInsteadOfWritingEmptyDumpWhenMariadbDumpCannotConnect(): void
    {
        // Connection farlocca: host irraggiungibile → mariadb-dump fallisce subito.
        // Con la vecchia pipeline `mariadb-dump ... | gzip > file` si sarebbe
        // comunque scritto un .sql.gz quasi vuoto riportando successo.
        $brokenDb = $this->createMock(Connection::class);
        $brokenDb->method('getParams')->willReturn([
            'host' => '127.0.0.1',
            'port' => 1, // porta chiusa: connection refused immediato
            'user' => 'autocron',
            'password' => 'wrong',
            'dbname' => 'autocron',
        ]);

        $command = new BackupCreateCommand($brokenDb, '/tmp', $this->backupsDir);
        $tester = new CommandTester($command);

        $threw = false;
        try {
            $tester->execute(['--skip-uploads' => true]);
        } catch (ProcessFailedException) {
            $threw = true;
        }

        self::assertTrue($threw, 'Un mariadb-dump fallito deve far fallire rumorosamente il comando, non passare inosservato');

        // Il file parziale viene rimosso: un backup troncato non deve restare sul
        // disco a sembrare valido (né essere scambiato per l'ultimo backup buono).
        self::assertSame([], glob($this->backupsDir.'/db-*.sql.gz') ?: []);
    }
}
