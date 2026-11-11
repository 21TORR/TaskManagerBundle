<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\EventListener\StopWorkerOnRestartSignalListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Tests\Torr\TaskManager\Fixtures\ListableTestTransport;
use Tests\Torr\TaskManager\Fixtures\TestEntityManagerFactory;
use Torr\TaskManager\Command\ClearQueueCommand;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Queue\QueueManager;
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
		$queue = ListableTestTransport::withMessages(3);
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
		$queue = ListableTestTransport::withMessages(1_234);
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
		$failed = ListableTestTransport::withMessages(2);
		$scheduler = ListableTestTransport::withMessages(2);

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
			"scheduler_default" => ListableTestTransport::withMessages(1),
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
		$app = ListableTestTransport::withMessages(2);
		$other = ListableTestTransport::withMessages(3);
		$failed = ListableTestTransport::withMessages(4);
		$scheduler = ListableTestTransport::withMessages(5);

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
		[$tester] = $this->createTester(["app" => ListableTestTransport::withMessages(1)]);

		self::assertSame(Command::FAILURE, $tester->execute([], ["interactive" => false]));
		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["app"], "--all" => true]));
		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["unknown"]]));
	}

	/**
	 *
	 */
	public function testFailsIfWorkersCanNotBeRestarted () : void
	{
		$queue = ListableTestTransport::withMessages(2);
		$helper = new TransportsHelper(
			new ServiceLocator(["app" => static fn () => $queue]),
			new BundleConfig([]),
			self::createStub(SendersLocatorInterface::class),
		);

		$application = new Application();
		$application->addCommand(new ClearQueueCommand($helper, self::createQueueManager($helper)));
		$tester = new CommandTester($application->find("task-manager:clear-queue"));

		self::assertSame(Command::FAILURE, $tester->execute(["queues" => ["app"], "--force" => true]));
		self::assertSame(0, $queue->getMessageCount());
		self::assertStringContainsString("Could not restart the workers", $tester->getDisplay());
	}

	/**
	 *
	 */
	private static function createQueueManager (TransportsHelper $helper) : QueueManager
	{
		return new QueueManager($helper, new TaskLogModel(TestEntityManagerFactory::create(), new NullLogger()));
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
		$application->addCommand(new ClearQueueCommand($helper, self::createQueueManager($helper)));

		return [new CommandTester($application->find("task-manager:clear-queue")), $cache];
	}
}
