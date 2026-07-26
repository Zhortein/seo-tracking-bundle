<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Zhortein\SeoTrackingBundle\DataLifecycle\Grouping\GroupingBackfillOptions;
use Zhortein\SeoTrackingBundle\DataLifecycle\Grouping\HistoricalGroupingKeyBackfiller;

#[AsCommand(
    name: 'zhortein:seo-tracking:backfill-grouping-keys',
    description: 'Inspect or backfill historical page-call grouping keys.',
)]
final class BackfillGroupingKeysCommand extends Command
{
    public function __construct(private readonly HistoricalGroupingKeyBackfiller $backfiller)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Persist the backfilled keys.')
            ->addOption(
                'merge-duplicates',
                null,
                InputOption::VALUE_NONE,
                'Merge duplicate default entities. Custom entities require a custom merger service.',
            )
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Rows processed between flushes.', '100')
            ->setHelp(<<<'HELP'
The command is read-only by default. Run it without options to inventory historical
rows, then use --apply to persist non-conflicting keys. Duplicate rows remain
untouched unless --merge-duplicates is also supplied.
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = filter_var($input->getOption('batch-size'), FILTER_VALIDATE_INT);
        if (!is_int($batchSize) || $batchSize < 1) {
            $io->error('The --batch-size option must be a positive integer.');

            return self::INVALID;
        }

        $apply = true === $input->getOption('apply');
        $mergeDuplicates = true === $input->getOption('merge-duplicates');
        if ($mergeDuplicates && !$apply) {
            $io->note('Dry-run: duplicate rows are only counted as merge candidates.');
        }

        try {
            $result = $this->backfiller->run(new GroupingBackfillOptions(
                $apply,
                $mergeDuplicates,
                $batchSize,
            ));
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $io->error($exception->getMessage());

            return self::FAILURE;
        }

        $io->table(
            ['Metric', $apply ? 'Applied' : 'Dry-run'],
            [
                ['Historical rows scanned', $result->scanned],
                [$apply ? 'Keys backfilled' : 'Rows safely backfillable', $result->backfillable],
                ['Duplicate conflicts', $result->conflicts],
                [$apply ? 'Duplicates merged' : 'Merge candidates', $result->merged],
                ['Invalid rows left unchanged', $result->invalid],
            ],
        );

        if (!$apply) {
            $io->warning('No data was changed. Re-run with --apply after reviewing the report and taking a backup.');
        } elseif ($result->conflicts > $result->merged) {
            $io->warning('Conflicting rows remain nullable. Re-run with an explicit consolidation policy if appropriate.');
        } else {
            $io->success('Historical grouping-key processing completed.');
        }

        return 0 === $result->invalid ? self::SUCCESS : self::FAILURE;
    }
}
