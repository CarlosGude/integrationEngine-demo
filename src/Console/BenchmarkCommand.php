<?php

declare(strict_types=1);

namespace App\Console;

use App\Catalog\Application\MovieCatalogGateway;
use App\Shared\Stats\Median;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:benchmark', description: 'Benchmark sequential vs. parallel TMDB requests')]
final class BenchmarkCommand extends Command
{
    public function __construct(
        private readonly MovieCatalogGateway $gateway,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'count',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Number of movies to load',
            5,
        );
        $this->addOption(
            'runs',
            'r',
            InputOption::VALUE_OPTIONAL,
            'Number of benchmark runs (default: 5)',
            5,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $count */
        $count = $input->getOption('count');
        /** @var string $runs */
        $runs = $input->getOption('runs');

        $movieCount = (int) $count;
        $runCount = (int) $runs;

        $movieIds = \array_slice(
            [299536, 550, 278, 496243, 680, 109, 129, 238, 240, 424, 389, 505642, 338762, 346698, 16662],
            0,
            $movieCount,
        );

        $io->section('IntegrationEngine Benchmark: Sequential vs. Parallel');
        $io->writeln("Loading {$movieCount} movies, {$runCount} runs each.\n");

        $parallelTimes = [];
        for ($i = 0; $i < $runCount; ++$i) {
            $start = \microtime(true);
            $this->gateway->getMoviesByIdBatch($movieIds);
            $parallelTimes[] = (\microtime(true) - $start) * 1000;
        }

        $parallelMedian = Median::calculate($parallelTimes);
        $parallelMin = \min($parallelTimes);
        $parallelMax = \max($parallelTimes);

        $io->table(
            ['Metric', 'Parallel Batch (ms)'],
            [
                ['Median', \number_format($parallelMedian, 2)],
                ['Min', \number_format($parallelMin, 2)],
                ['Max', \number_format($parallelMax, 2)],
            ],
        );

        $io->success('Benchmark complete!');

        return Command::SUCCESS;
    }
}
