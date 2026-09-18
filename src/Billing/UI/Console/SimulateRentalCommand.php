<?php

declare(strict_types=1);

namespace App\Billing\UI\Console;

use App\Billing\Application\RentalPaymentGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
            550, // Fight Club
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $movieId = (int) $input->getArgument('movie-id');

        $io->info("Simulating rental for movie #{$movieId}...");

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
                    ['Client Secret', '***' . substr($payment->clientSecret, -10)],
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
}
