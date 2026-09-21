<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'mercure:publish',
    description: 'Publish a message to Mercure topics',
)]
class MercurePublishCommand extends Command
{
    public function __construct(private HubInterface $hub)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('topic', InputArgument::REQUIRED, 'Topic to publish to')
            ->addArgument('message', InputArgument::REQUIRED, 'Message to publish (JSON)')
            ->addOption('repeat', 'r', InputOption::VALUE_REQUIRED, 'Repeat N times with delay', '1')
            ->addOption('delay', 'd', InputOption::VALUE_REQUIRED, 'Delay between messages (seconds)', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topic = (string) $input->getArgument('topic');
        $messageArg = (string) $input->getArgument('message');
        $repeat = (int) $input->getOption('repeat');
        $delay = (int) $input->getOption('delay');

        // Parse JSON message
        $message = json_decode($messageArg, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $io->error('Invalid JSON message: '.json_last_error_msg());
            return Command::FAILURE;
        }
        if (!is_array($message)) {
            $io->error('Message must be a JSON object, not '.gettype($message));
            return Command::FAILURE;
        }

        // Add timestamp
        $message['timestamp'] = date('c');

        for ($i = 0; $i < $repeat; $i++) {
            if ($i > 0) {
                sleep($delay);
            }

            $update = new Update(
                topics: $topic,
                data: (string) json_encode($message),
            );

            $this->hub->publish($update);
            $io->success(sprintf(
                'Published to "%s" (%d/%d): %s',
                $topic,
                $i + 1,
                $repeat,
                json_encode($message),
            ));
        }

        return Command::SUCCESS;
    }
}
