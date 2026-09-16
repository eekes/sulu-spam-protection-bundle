<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Eekes\Sulu\SpamProtectionBundle\Domain\Entity\BlockedSubmission;
use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Eekes\Sulu\SpamProtectionBundle\Tests\Double\FakeRecaptchaRequestMethod;
use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Boots the small test application and gives every test an empty database.
 *
 * The schema is built from the entity metadata rather than from a migration: the bundle ships no
 * migrations, because the project that installs it generates its own.
 */
abstract class FunctionalTestCase extends KernelTestCase
{
    protected KernelInterface $testKernel;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->testKernel = self::bootKernel();

        $entityManager = self::getContainer()->get('eekes_test.entity_manager');
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function google(): FakeRecaptchaRequestMethod
    {
        $google = self::getContainer()->get('eekes_test.google');
        \assert($google instanceof FakeRecaptchaRequestMethod);

        return $google;
    }

    protected function blockedSubmissionRepository(): BlockedSubmissionRepository
    {
        $repository = self::getContainer()->get('eekes_test.blocked_submission_repository');
        \assert($repository instanceof BlockedSubmissionRepository);

        return $repository;
    }

    protected function spamProtectionLog(): TestHandler
    {
        $handler = self::getContainer()->get('eekes_test.spam_protection_log');
        \assert($handler instanceof TestHandler);

        return $handler;
    }

    /**
     * @return list<BlockedSubmission>
     */
    protected function blockedSubmissions(): array
    {
        $this->entityManager->clear();

        return $this->entityManager
            ->getRepository(BlockedSubmission::class)
            ->findBy([], ['id' => 'ASC']);
    }
}
