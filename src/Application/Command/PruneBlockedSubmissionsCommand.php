<?php

declare(strict_types=1);

namespace Eekes\Sulu\SpamProtectionBundle\Application\Command;

use Eekes\Sulu\SpamProtectionBundle\Domain\Repository\BlockedSubmissionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Keeps the log of blocked submissions from growing forever. Meant to run from cron; the retention
 * comes from the bundle configuration unless the call overrides it.
 */
#[AsCommand(
    name: 'eekes:spam-protection:prune',
    description: 'Deletes blocked submissions that are older than the configured retention',
)]
final class PruneBlockedSubmissionsCommand extends Command
{
    public function __construct(
        private readonly BlockedSubmissionRepository $blockedSubmissionRepository,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                null,
                InputOption::VALUE_REQUIRED,
                'Overrides the configured retention in days',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Only reports how many submissions would be deleted',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $daysOption = $input->getOption('days');
        $days = \is_string($daysOption) ? (int) $daysOption : $this->retentionDays;

        if ($days < 1) {
            $io->warning('No retention is configured, so nothing is deleted. Set eekes_sulu_spam_protection.retention_days or pass --days.');

            return Command::SUCCESS;
        }

        $moment = new \DateTimeImmutable(\sprintf('-%d days', $days));

        if ($input->getOption('dry-run') === true) {
            $io->success(\sprintf(
                '%d blocked submissions from before %s would be deleted.',
                $this->blockedSubmissionRepository->countOlderThan($moment),
                $moment->format('d/m/Y H:i'),
            ));

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            '%d blocked submissions from before %s were deleted.',
            $this->blockedSubmissionRepository->deleteOlderThan($moment),
            $moment->format('d/m/Y H:i'),
        ));

        return Command::SUCCESS;
    }
}
