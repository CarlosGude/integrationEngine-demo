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

        try {
            [$topic, $message, $repeat, $delay] = $this->parseInput($input);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $message['timestamp'] = date('c');

        for ($i = 0; $i < $repeat; ++$i) {
            if ($i > 0) {
                sleep($delay);
            }

            $encodedMessage = json_encode($message, \JSON_THROW_ON_ERROR);
            $this->hub->publish(new Update(topics: $topic, data: $encodedMessage));
            $io->success(sprintf(
                'Published to "%s" (%d/%d): %s',
                $topic,
                $i + 1,
                $repeat,
                $encodedMessage,
            ));
        }

        return Command::SUCCESS;
    }

    /** @return array{string, array<mixed>, int, int} */
    private function parseInput(InputInterface $input): array
    {
        $topic = $input->getArgument('topic');
        $messageArg = $input->getArgument('message');
        $repeat = $input->getOption('repeat');
        $delay = $input->getOption('delay');

        if (!is_string($topic) || !is_string($messageArg) || !is_numeric($repeat) || !is_numeric($delay)) {
            throw new \InvalidArgumentException('Invalid input types');
        }

        $message = json_decode($messageArg, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON message: '.json_last_error_msg());
        }
        if (!is_array($message)) {
            throw new \InvalidArgumentException('Message must be a JSON object, not '.gettype($message));
        }

        return [$topic, $message, (int) $repeat, (int) $delay];
    }

}
