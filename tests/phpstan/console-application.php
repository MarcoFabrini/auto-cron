<?php
// Loader per phpstan-symfony: serve a far conoscere a PHPStan
// i nomi dei console command custom.

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

$kernel = new Kernel('dev', false);
$kernel->boot();
return new Application($kernel);
