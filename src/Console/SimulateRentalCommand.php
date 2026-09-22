<?php

declare(strict_types=1);

namespace App\Console;

use App\Billing\Application\RentalPaymentGateway;
use App\Shared\Infrastructure\ChaosMonkey;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'billing:simulate-rental',
    description: 'Simulate renting a movie and creating a Stripe payment intent.',
)]
final class SimulateRentalCommand extends Command
{
    public function __construct(
        private readonly RentalPaymentGateway $gateway,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'movie-id',
            InputArgument::OPTIONAL,
            'TMDB movie ID to rent',
            '550', // Fight Club
        );
        $this->addOption(
            'chaos',
            'c',
            InputOption::VALUE_NONE,
            'Inject random failures to test resilience patterns',
        );
        $this->addOption(
            'timeout-rate',
            null,
            InputOption::VALUE_OPTIONAL,
            'Timeout failure rate (0-100%)',
            '30',
        );
        $this->addOption(
            'ratelimit-rate',
            null,
            InputOption::VALUE_OPTIONAL,
            'Rate limit failure rate (0-100%)',
            '20',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $movieIdArgument = $input->getArgument('movie-id');
        $movieId = \is_numeric($movieIdArgument) ? (int) $movieIdArgument : 0;
        $useChaos = $input->getOption('chaos');

        $io->info("Simulating rental for movie #{$movieId}...");

        if ($useChaos) {
            return $this->executeWithChaos($io, $movieId, $input);
        }

        return $this->executeNormal($io, $movieId);
    }

    private function executeNormal(SymfonyStyle $io, int $movieId): int
    {
        try {
            $payment = $this->gateway->rentMovie(movieId: $movieId, amountCents: 500, currency: 'usd');

            $io->success('Payment intent created!');
            $io->table(
                ['Field', 'Value'],
                [
                    ['Payment Intent ID', $payment->paymentIntentId],
                    ['Movie ID', $payment->movieId],
                    ['Amount', sprintf('%d cents (%s)', $payment->amountCents, strtoupper($payment->currency))],
                    ['Status', $payment->status],
                    ['Client Secret', '***'.substr($payment->clientSecret, -10)],
                ],
            );

            $io->newLine();
            $io->note('In a real app, this client_secret would be sent to the frontend for payment confirmation.');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Failed to create payment intent: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    // tour:start resilience/chaos-testing-example
    private function executeWithChaos(SymfonyStyle $io, int $movieId, InputInterface $input): int
    {
        $timeoutRate = (int) ($input->getOption('timeout-rate') ?? '0');
        $rateLimitRate = (int) ($input->getOption('ratelimit-rate') ?? '0');
        $chaos = (new ChaosMonkey())
            ->withTimeoutRate($timeoutRate)
            ->withRateLimitRate($rateLimitRate);

        $io->warning('🔴 CHAOS MODE ENABLED');
        $io->text([
            sprintf('  Timeout failures: %d%%', $timeoutRate),
            sprintf('  Rate limit failures: %d%%', $rateLimitRate),
        ]);

        $results = $this->simulateAttemptsWithChaos($io, $movieId, $chaos);

        $this->displayResults($io, $results);

        return $results['successes'] > 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<string, array<array<string, bool|int|string>>|int>
     */
    private function simulateAttemptsWithChaos(SymfonyStyle $io, int $movieId, ChaosMonkey $chaos): array
    {
        $attempts = 0;
        $successes = 0;
        $failures = [];

        for ($i = 1; $i <= 5; ++$i) {
            ++$attempts;
            $io->text(sprintf("Attempt {$i}/5..."));

            if ($this->processAttempt($io, $i, $movieId, $chaos, $failures, $successes)) {
                break;
            }
        }

        return ['attempts' => $attempts, 'successes' => $successes, 'failures' => $failures];
    }

    /**
     * @param array<array<string, bool|int|string>> $failures
     */
    private function processAttempt(SymfonyStyle $io, int $i, int $movieId, ChaosMonkey $chaos, array &$failures, int &$successes): bool
    {
        try {
            if ($chaos->shouldTimeout()) {
                $failures[] = ['attempt' => $i, 'error' => 'Timeout (transient)', 'retryable' => true];
                $chaos->injectTimeout();
            }

            if ($chaos->shouldRateLimit()) {
                $failures[] = ['attempt' => $i, 'error' => '429 Rate Limit (transient)', 'retryable' => true];
                $chaos->injectRateLimit();
            }

            if ($i % 2 === 1 && random_int(1, 100) > 40) {
                $this->gateway->rentMovie(movieId: $movieId, amountCents: 500, currency: 'usd');
                ++$successes;
                $io->writeln('  <info>✓ Success</info>');
                return true;
            }
        } catch (\Throwable $e) {
            $isRetryable = $this->isRetryable($e);
            $failures[] = ['attempt' => $i, 'error' => $e->getMessage(), 'retryable' => $isRetryable];
            $status = $isRetryable ? '<fg=yellow>⟳ Transient</>' : '<fg=red>✗ Permanent</>';
            $io->writeln("  {$status}: {$e->getMessage()}");

            if (!$isRetryable) {
                return true;
            }

            usleep(100 * (1 << ($i - 1)) * 1000);
        }

        return false;
    }

    /**
     * @param array<string, array<array<string, bool|int|string>>|int> $results
     */
    private function displayResults(SymfonyStyle $io, array $results): void
    {
        $io->newLine();

        $successes = (int) ($results['successes'] ?? 0);
        $attempts = (int) ($results['attempts'] ?? 0);
        $failures = (array) ($results['failures'] ?? []);

        if ($successes > 0) {
            $io->success('Eventually succeeded! This demonstrates retry resilience.');
            $io->section('Resilience Summary');
            $io->text([
                "✓ Survived {$successes} out of {$attempts} attempts",
                "⟳ Retried on transient errors (timeouts, rate limits)",
                "✓ Recovered after failures via exponential backoff",
            ]);
        } else {
            $io->warning('All attempts exhausted.');
        }

        if (!empty($failures)) {
            $io->section('Failure Log');
            $rows = [];
            foreach ($failures as $f) {
                if (is_array($f) && isset($f['attempt'], $f['error'], $f['retryable'])) {
                    $rows[] = [
                        $f['attempt'],
                        $f['error'],
                        $f['retryable'] ? 'Yes (⟳)' : 'No (✗)',
                    ];
                }
            }
            $io->table(['Attempt', 'Error', 'Retryable?'], $rows);
        }

        $io->newLine();
        $io->note('In production, RetryMiddleware + CircuitBreaker would handle this automatically.');
    }

    private function isRetryable(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'timeout')
            || str_contains($message, 'Too Many Requests')
            || str_contains($message, 'Service Unavailable');
    }
    // tour:end
}
