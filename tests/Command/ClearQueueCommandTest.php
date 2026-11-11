<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Torr\TaskManager\Command\ClearQueueCommand;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @internal
 */
final class ClearQueueCommandTest extends TestCase
{
	/**
	 *
	 */
	public function testDryRunDoesNotRemoveAnything () : void
	{
		$queue = $this->createListableTransport(3);
		[$tester, $cache] = $this->createTester(["app" => $queue]);

		$tester->execute(["queues" => ["app"]]);

		self::assertSame(Command::SUCCESS, $tester->getStatusCode());
		self::assertStringContainsString("3 tasks would be removed", $tester->getDisplay());
		self::assertSame(3, $queue->getMessageCount());
		self::assertFalse($cache->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
	}

	/**
	 *
	 */
	public function testForceRemovesAllMessagesInBatches () : void
	{
		$queue = $this->createListableTransport(1_234);
		[$tester, $cache] = $this->createTester(["app" => $queue]);

		$tester->execute(["queues" => ["app"], "--force" => true]);

		self::assertSame(Command::SUCCESS, $tester->getStatusCode());
		self::assertSame(0, $queue->getMessageCount());
		self::assertStringContainsString("Removed 1234 tasks", $tester->getDisplay());
		self::assertTrue($cache->hasItem(StopWorkerOnRestartSignalListener::RESTART_REQUESTED_TIMESTAMP_KEY));
	}

	/**
	 *
	 */
	public function testAllSkipsFailureAndSchedulerQueues () : void
	{
		$app = new InMemoryTransport();
		$app->send(new Envelope(new \stdClass()));
		$app->send(new Envelope(new \stdClass()));
		$failed = $this->createListableTransport(2);
		$scheduler = $this->createListableTransport(2);

		[$tester] = $this->createTester(
			["app" => $app, "failed" => $failed, "scheduler_default" => $scheduler],
			["failed"],
		);

		$tester->execute(["--all" => true, "--force" => true]);

		self::assertSame(Command::SUCCESS, $tester->getStatusCode());
		self::assertCount(0, $app->get());
		self::assertCount(2, $app->getRejected());
		self::assertSame(2, $failed->getMessageCount());
		self::assertSame(2, $scheduler->getMessageCount());
		self::assertStringContainsString("failure queue, clear it explicitly", $tester->getDisplay());
	}

	/**
	 *
	 */
	public function testExplainsWhyNoQueuesCanBeCleared () : void
	{
		$sync = new SyncTransport(self::createStub(MessageBusInterface::class));

		[$tester] = $this->createTester([
			"app" => $sync,
			"app_urgent" => $sync,
			"scheduler_default" => $this->createListableTransport(1),
			TransportsHelper::INTERNAL_TRANSPORT_NAME => $sync,
		]);

		self::assertSame(Command::SUCCESS, $tester->execute(["--all" => true]));
		$display = $tester->getDisplay();
		self::assertStringContainsString("No queues to clear.", $display);
		self::assertStringContainsString("app, app_urgent: uses a sync transport", $display);
		self::assertStringContainsString("scheduler_default: scheduler queue", $display);
		self::assertStringContainsString("internal queue of the task manager", $display);

		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["app"]]));
		self::assertStringContainsString("Can't clear queue 'app': uses a sync transport", $tester->getDisplay());
	}

	/**
	 *
	 */
	public function testInteractivelySelectQueues () : void
	{
		$app = $this->createListableTransport(2);
		$other = $this->createListableTransport(3);
		$failed = $this->createListableTransport(4);
		$scheduler = $this->createListableTransport(5);

		[$tester] = $this->createTester(
			["app" => $app, "other" => $other, "failed" => $failed, "scheduler_default" => $scheduler],
			["failed"],
		);

		$tester->setInputs(["app,failed"]);
		$tester->execute(["--force" => true]);

		self::assertSame(Command::SUCCESS, $tester->getStatusCode());
		$display = $tester->getDisplay();
		self::assertStringContainsString("Which queues do you want to clear?", $display);
		self::assertStringContainsString("2 tasks", $display);
		// failure transports can be selected explicitly, scheduler queues can't be cleared at all
		self::assertStringContainsString("failed", $display);
		self::assertStringNotContainsString("scheduler_default", $display);
		self::assertSame(0, $app->getMessageCount());
		self::assertSame(0, $failed->getMessageCount());
		self::assertSame(3, $other->getMessageCount());
	}

	/**
	 *
	 */
	public function testInvalidInput () : void
	{
		[$tester] = $this->createTester(["app" => $this->createListableTransport(1)]);

		self::assertSame(Command::FAILURE, $tester->execute([], ["interactive" => false]));
		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["app"], "--all" => true]));
		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["unknown"]]));
	}

	/**
	 *
	 */
	public function testFailsIfWorkersCanNotBeRestarted () : void
	{
		$queue = $this->createListableTransport(2);
		$helper = new TransportsHelper(
			new ServiceLocator(["app" => static fn () => $queue]),
			new BundleConfig([]),
			self::createStub(SendersLocatorInterface::class),
		);

		$application = new Application();
		$application->addCommand(new ClearQueueCommand($helper, new BundleConfig([])));
		$tester = new CommandTester($application->find("task-manager:clear-queue"));

		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["app"], "--force" => true]));
		self::assertSame(0, $queue->getMessageCount());
		self::assertStringContainsString("Could not restart the workers", $tester->getDisplay());
	}

	/**
	 * @param array<string, TransportInterface> $transports
	 * @param string[]                          $failureTransports
	 *
	 * @return array{CommandTester, ArrayAdapter}
	 */
	private function createTester (array $transports, array $failureTransports = []) : array
	{
		$helper = new TransportsHelper(
			new ServiceLocator(array_map(
				static fn (TransportInterface $transport) => static fn () => $transport,
				$transports,
			)),
			new BundleConfig([], $failureTransports),
			self::createStub(SendersLocatorInterface::class),
		);

		$cache = new ArrayAdapter();
		$application = new Application();
		$application->addCommand(new StopWorkersCommand($cache));
		$application->addCommand(new ClearQueueCommand($helper, new BundleConfig([], $failureTransports)));

		return [new CommandTester($application->find("task-manager:clear-queue")), $cache];
	}

	/**
	 *
	 */
	private function createListableTransport (int $count) : TransportInterface&ListableReceiverInterface&MessageCountAwareInterface
	{
		return new class($count) implements TransportInterface, ListableReceiverInterface, MessageCountAwareInterface {
			/** @var array<int, Envelope> */
			private array $messages = [];

			public function __construct (int $count)
			{
				for ($i = 1; $i <= $count; ++$i)
				{
					$this->messages[$i] = new Envelope(new \stdClass(), [new TransportMessageIdStamp($i)]);
				}
			}

			public function get () : iterable
			{
				return [];
			}

			public function ack (Envelope $envelope) : void {}

			public function reject (Envelope $envelope) : void
			{
				$id = $envelope->last(TransportMessageIdStamp::class)?->getId();

				if (\is_int($id))
				{
					unset($this->messages[$id]);
				}
			}

			public function send (Envelope $envelope) : Envelope
			{
				return $envelope;
			}

			public function all (?int $limit = null) : iterable
			{
				return \array_slice($this->messages, 0, $limit, true);
			}

			public function find (mixed $id) : ?Envelope
			{
				return \is_int($id)
					? ($this->messages[$id] ?? null)
					: null;
			}

			public function getMessageCount () : int
			{
				return \count($this->messages);
			}
		};
	}
}
