<?php

declare(strict_types=1);

namespace Tests\Command;

use App\Command\MercurePublishCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final class MercurePublishCommandTest extends TestCase
{
    #[Test]
    public function publishesASingleUpdateWithATimestamp(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $published = null;
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::isInstanceOf(Update::class))
            ->willReturnCallback(static function (Update $update) use (&$published): string {
                $published = $update;

                return 'id';
            });

        $tester = new CommandTester(new MercurePublishCommand($hub));
        $exitCode = $tester->execute([
            'topic' => 'movies/550',
            'message' => '{"title":"Fight Club"}',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertInstanceOf(Update::class, $published);
        self::assertSame(['movies/550'], $published->getTopics());

        /** @var array<string, mixed> $data */
        $data = json_decode($published->getData(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Fight Club', $data['title']);
        self::assertArrayHasKey('timestamp', $data);

        self::assertStringContainsString('Published to "movies/550" (1/1)', $tester->getDisplay());
    }

    #[Test]
    public function invalidJsonMessageFails(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $tester = new CommandTester(new MercurePublishCommand($hub));
        $exitCode = $tester->execute([
            'topic' => 'movies/550',
            'message' => '{not valid json',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Invalid JSON message', $tester->getDisplay());
    }

    #[Test]
    public function nonObjectJsonMessageFails(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $tester = new CommandTester(new MercurePublishCommand($hub));
        $exitCode = $tester->execute([
            'topic' => 'movies/550',
            'message' => '"just a string"',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Message must be a JSON object, not string', $tester->getDisplay());
    }

    #[Test]
    public function nonNumericRepeatOptionFails(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $tester = new CommandTester(new MercurePublishCommand($hub));
        $exitCode = $tester->execute([
            'topic' => 'movies/550',
            'message' => '{}',
            '--repeat' => 'not-a-number',
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Invalid input types', $tester->getDisplay());
    }

    #[Test]
    public function repeatsPublishingWithoutDelayTheExactRequestedNumberOfTimes(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::exactly(3))->method('publish')->willReturn('id');

        $tester = new CommandTester(new MercurePublishCommand($hub));
        $exitCode = $tester->execute([
            'topic' => 'movies/550',
            'message' => '{}',
            '--repeat' => '3',
            '--delay' => '0',
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('(1/3)', $tester->getDisplay());
        self::assertStringContainsString('(2/3)', $tester->getDisplay());
        self::assertStringContainsString('(3/3)', $tester->getDisplay());
    }
}
