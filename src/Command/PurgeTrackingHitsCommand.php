<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zhortein\SeoTrackingBundle\DataLifecycle\Retention\HitRetentionPurgerInterface;
use Zhortein\SeoTrackingBundle\DataLifecycle\Retention\RetentionPolicy;
use Zhortein\SeoTrackingBundle\DataLifecycle\Retention\RetentionPurgeOptions;

#[AsCommand(
    name: 'zhortein:seo-tracking:purge',
    description: 'Preview or purge tracking hits older than the configured or explicit cutoff.',
)]
final class PurgeTrackingHitsCommand extends Command
{
    public function __construct(
        private readonly HitRetentionPurgerInterface $purger,
        private readonly RetentionPolicy $policy,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Persist the deletion and aggregate updates.')
            ->addOption(
                'before',
                null,
                InputOption::VALUE_REQUIRED,
                'Absolute cutoff understood by DateTimeImmutable, for example 2026-01-01T00:00:00+00:00.',
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Override the configured batch size.')
            ->addOption(
                'remove-empty-page-calls',
                null,
                InputOption::VALUE_NONE,
                'Remove page-call aggregates emptied by this purge.',
            )
            ->setHelp(<<<'HELP'
The command is read-only by default. It requires either retention.days in bundle
configuration or an explicit --before value. Hits without a calledAt date are
reported and left unchanged.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $cutoff = $this->cutoff($input);
            $batchSize = $this->batchSize($input);
            $result = $this->purger->purge(new RetentionPurgeOptions(
                $cutoff,
                true === $input->getOption('apply'),
                $batchSize,
                $this->policy->removeEmptyPageCalls || true === $input->getOption('remove-empty-page-calls'),
            ));
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->table(
            ['Metric', $result->applied ? 'Applied' : 'Dry-run'],
            [
                ['Cutoff', $result->cutoff->format(\DateTimeInterface::ATOM)],
                ['Candidate hits', $result->candidateHits],
                ['Purged hits', $result->purgedHits],
                ['Affected page calls', $result->affectedPageCalls],
                ['Removed empty page calls', $result->removedPageCalls],
                ['Undated hits left unchanged', $result->undatedHits],
            ],
        );

        if (!$result->applied) {
            $io->warning('No data was changed. Re-run with --apply after reviewing the report and taking a backup.');
        } else {
            $io->success('Tracking retention completed.');
        }

        return self::SUCCESS;
    }

    private function cutoff(InputInterface $input): \DateTimeImmutable
    {
        $before = $input->getOption('before');
        if (is_string($before) && '' !== trim($before)) {
            try {
                return new \DateTimeImmutable($before);
            } catch (\Exception $exception) {
                throw new \InvalidArgumentException(sprintf('Invalid --before value: %s', $exception->getMessage()), 0, $exception);
            }
        }

        if (null === $this->policy->days) {
            throw new \InvalidArgumentException('Retention is disabled: configure retention.days or pass --before.');
        }

        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->sub(new \DateInterval(sprintf('P%dD', $this->policy->days)));
    }

    private function batchSize(InputInterface $input): int
    {
        $batchSize = $input->getOption('batch-size');
        if (null === $batchSize) {
            return $this->policy->batchSize;
        }

        $validated = filter_var($batchSize, FILTER_VALIDATE_INT);
        if (!is_int($validated) || $validated < 1) {
            throw new \InvalidArgumentException('The --batch-size option must be a positive integer.');
        }

        return $validated;
    }
}
