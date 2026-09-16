<?php

declare(strict_types=1);

use Doctrine\Persistence\ManagerRegistry;
use Eekes\Sulu\SpamProtectionBundle\Tests\Application\Kernel;

require __DIR__.'/bootstrap.php';

$kernel = new Kernel('test', false);
$kernel->boot();

$registry = $kernel->getContainer()->get('doctrine');
\assert($registry instanceof ManagerRegistry);

return $registry->getManager();
