<?php

declare(strict_types=1);

namespace Tests\Console;

use App\Console\SimulateRentalCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SimulateRentalCommandTest extends KernelTestCase
{
    private function successResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'id' => 'pi_test123',
            'client_secret' => 'pi_test123_secret_abc',
            'status' => 'requires_payment_method',
            'amount' => 500,
            'currency' => 'usd',
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }

    private function commandTester(MockHttpClient $httpClient): CommandTester
    {
        self::bootKernel();
        $container = self::getContainer();
        $container->set('http_client', $httpClient);

        $command = $container->get(SimulateRentalCommand::class);
        \assert($command instanceof SimulateRentalCommand);

        return new CommandTester($command);
    }

    #[Test]
    public function normalRentalSucceeds(): void
    {
        $tester = $this->commandTester(new MockHttpClient([$this->successResponse()]));

        $exitCode = $tester->execute(['movie-id' => '550']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('Simulating rental for movie #550', $display);
        self::assertStringContainsString('Payment intent created!', $display);
        self::assertStringContainsString('pi_test123', $display);
        // Only the last 10 characters of the client secret are shown.
        self::assertStringContainsString('***secret_abc', $display);
        self::assertStringNotContainsString('pi_test123_secret_abc', $display);
    }

    #[Test]
    public function nonNumericMovieIdDefaultsToZero(): void
    {
        $tester = $this->commandTester(new MockHttpClient([$this->successResponse()]));

        $tester->execute(['movie-id' => 'not-a-number']);

        self::assertStringContainsString('Simulating rental for movie #0', $tester->getDisplay());
    }

    #[Test]
    public function normalRentalFailureIsReportedAndDoesNotThrow(): void
    {
        $tester = $this->commandTester(new MockHttpClient([
            new MockResponse('{"error":{"message":"card declined"}}', ['http_code' => 402]),
        ]));

        $exitCode = $tester->execute(['movie-id' => '550']);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Failed to create payment intent', $tester->getDisplay());
    }

    #[Test]
    public function chaosModeExhaustsAllAttemptsOnForcedTimeouts(): void
    {
        // timeout-rate=100 makes shouldTimeout() deterministic (always
        // true), so every one of the 5 attempts fails with a retryable
        // timeout and none ever reaches the real HTTP call.
        $tester = $this->commandTester(new MockHttpClient([]));

        $exitCode = $tester->execute([
            'movie-id' => '550',
            '--chaos' => true,
            '--timeout-rate' => '100',
            '--ratelimit-rate' => '0',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('CHAOS MODE ENABLED', $display);
        self::assertStringContainsString('All attempts exhausted.', $display);
        self::assertSame(5, substr_count($display, 'Timeout (transient)'));
    }

    #[Test]
    public function chaosModeExhaustsAllAttemptsOnForcedRateLimits(): void
    {
        $tester = $this->commandTester(new MockHttpClient([]));

        $exitCode = $tester->execute([
            'movie-id' => '550',
            '--chaos' => true,
            '--timeout-rate' => '0',
            '--ratelimit-rate' => '100',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        $display = $tester->getDisplay();
        self::assertStringContainsString('All attempts exhausted.', $display);
        self::assertSame(5, substr_count($display, '429 Rate Limit (transient)'));
    }
}
