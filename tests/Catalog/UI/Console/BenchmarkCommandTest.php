<?php

declare(strict_types=1);

namespace Tests\Catalog\UI\Console;

use App\Console\BenchmarkCommand;
use App\Shared\Stats\Median;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class BenchmarkCommandTest extends KernelTestCase
{
    #[Test]
    public function benchmarkResultsAreCalculatedCorrectly(): void
    {
        $times = [100, 150, 120, 110, 130];
        $median = Median::calculate($times);

        self::assertGreaterThanOrEqual(100, $median);
        self::assertLessThanOrEqual(150, $median);
    }

    #[Test]
    public function benchmarkCanHandleVariableExecutionTimes(): void
    {
        $times = [100.5, 200.3, 150.7, 175.2, 125.9];
        $median = Median::calculate($times);

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertIsFloat($median);
        self::assertGreaterThan(0, $median);
    }

    #[Test]
    public function benchmarkMetricsAreReasonable(): void
    {
        $parallelTimes = [250, 260, 245, 255, 250];
        $median = Median::calculate($parallelTimes);
        $min = \min($parallelTimes);
        $max = \max($parallelTimes);

        self::assertSame(250.0, $median);
        self::assertSame(245, $min);
        self::assertSame(260, $max);
    }

    #[Test]
    public function commandRunsTheBenchmarkAndPrintsTheTable(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $movieJson = json_encode([
            'id' => 550,
            'title' => 'Fight Club',
            'overview' => 'Overview',
            'poster_path' => '/poster.jpg',
            'vote_average' => 8.4,
            'release_date' => '1999-10-15',
        ], \JSON_THROW_ON_ERROR);
        $configJson = json_encode([
            'images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/', 'poster_sizes' => ['w500']],
        ], \JSON_THROW_ON_ERROR);

        // count=1, runs=2 => 2 iterations, each doing one movie batch call
        // (1 movie request + 1 trailing configuration request).
        $mockClient = new MockHttpClient([
            new MockResponse($movieJson, ['http_code' => 200]),
            new MockResponse($configJson, ['http_code' => 200]),
            new MockResponse($movieJson, ['http_code' => 200]),
            new MockResponse($configJson, ['http_code' => 200]),
        ]);
        $container->set('http_client', $mockClient);

        $command = $container->get(BenchmarkCommand::class);
        \assert($command instanceof BenchmarkCommand);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--count' => '1', '--runs' => '2']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Loading 1 movies, 2 runs each.', $display);
        self::assertStringContainsString('Benchmark complete!', $display);
        self::assertStringContainsString('Median', $display);
        self::assertStringContainsString('Min', $display);
        self::assertStringContainsString('Max', $display);
    }

    #[Test]
    public function commandRejectsAZeroRunCount(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $command = $container->get(BenchmarkCommand::class);
        \assert($command instanceof BenchmarkCommand);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--runs' => '0']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('--runs must be at least 1.', $tester->getDisplay());
    }

    #[Test]
    public function commandRejectsANegativeRunCount(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $command = $container->get(BenchmarkCommand::class);
        \assert($command instanceof BenchmarkCommand);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['--runs' => '-3']);

        self::assertSame(Command::INVALID, $exitCode);
    }
}
