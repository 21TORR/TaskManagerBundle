<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Queue;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Tests\Torr\TaskManager\Fixtures\ExampleTask;
use Tests\Torr\TaskManager\Fixtures\ListableTestTransport;
use Tests\Torr\TaskManager\Fixtures\TestEntityManagerFactory;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Exception\Queue\QueuedTaskAlreadyStartedException;
use Torr\TaskManager\Exception\Queue\QueuedTaskNotFoundException;
use Torr\TaskManager\Exception\Queue\UnknownQueueException;
use Torr\TaskManager\Exception\Queue\UnsupportedQueueOperationException;
use Torr\TaskManager\Identification\TaskIdStamp;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Queue\QueuedTaskId;
use Torr\TaskManager\Queue\QueueManager;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @internal
 */
final class QueueManagerTest extends TestCase
{
	private TaskLogModel $logModel;

	/**
	 *
	 */
	#[\Override]
	protected function setUp () : void
	{
		$this->logModel = new TaskLogModel(TestEntityManagerFactory::create(), new NullLogger());
	}

	/**
	 *
	 */
	public function testListQueuedTasks () : void
	{
		$queue = new ListableTestTransport([
			new Envelope(new ExampleTask("First"), [new TaskIdStamp("task-1")]),
			new Envelope(new ExampleTask("Second", "imports"), [new TaskIdStamp("task-2"), new RedeliveryStamp(2)]),
			new \stdClass(),
		]);
		$this->startTask("task-1");

		$listing = $this->createManager(["app" => $queue])->getQueuedTasksOfQueue("app");

		self::assertSame("app", $listing->queue);
		self::assertTrue($listing->supportsListing);
		self::assertFalse($listing->isFailureQueue);
		self::assertSame(3, $listing->totalCount);
		self::assertFalse($listing->hasMore);
		self::assertCount(3, $listing->tasks);

		[$first, $second, $third] = $listing->tasks;

		self::assertSame("app:1", $first->id?->toString());
		self::assertSame("First", $first->label);
		self::assertSame("task-1", $first->taskId);
		self::assertTrue($first->isStarted);
		self::assertFalse($first->isRemovable());

		self::assertSame("Second", $second->label);
		self::assertSame("imports", $second->group);
		self::assertSame(2, $second->retryCount);
		self::assertFalse($second->isStarted);
		self::assertTrue($second->isRemovable());

		self::assertSame("stdClass", $third->label);
		self::assertNull($third->taskId);
		self::assertTrue($third->isRemovable());
	}

	/**
	 *
	 */
	public function testListingIsLimited () : void
	{
		$manager = $this->createManager(["app" => ListableTestTransport::withMessages(5)]);

		$listing = $manager->getQueuedTasksOfQueue("app", 2);
		self::assertCount(2, $listing->tasks);
		self::assertTrue($listing->hasMore);
		self::assertSame(5, $listing->totalCount);

		$listing = $manager->getQueuedTasksOfQueue("app", 5);
		self::assertCount(5, $listing->tasks);
		self::assertFalse($listing->hasMore);

		$listing = $manager->getQueuedTasksOfQueue("app", 0);
		self::assertCount(0, $listing->tasks);
		self::assertTrue($listing->hasMore);
	}

	/**
	 *
	 */
	public function testListAllQueues () : void
	{
		$nonListable = self::createStub(TransportInterface::class);

		$manager = $this->createManager(
			[
				"app" => ListableTestTransport::withMessages(1),
				"failed" => ListableTestTransport::withMessages(1),
				"amqp" => $nonListable,
				"sync" => new SyncTransport(self::createStub(MessageBusInterface::class)),
				"scheduler_default" => ListableTestTransport::withMessages(1),
			],
			["failed"],
		);

		$listings = $manager->getQueuedTasks();
		self::assertSame(["app", "amqp"], array_map(static fn ($listing) => $listing->queue, $listings));
		self::assertFalse($listings[1]->supportsListing);
		self::assertNull($listings[1]->totalCount);
		self::assertSame([], $listings[1]->tasks);

		$listings = $manager->getQueuedTasks(includeFailureQueues: true);
		self::assertSame(["app", "failed", "amqp"], array_map(static fn ($listing) => $listing->queue, $listings));
		self::assertTrue($listings[1]->isFailureQueue);
	}

	/**
	 *
	 */
	public function testInMemoryTransport () : void
	{
		$queue = new InMemoryTransport();
		$queue->send(new Envelope(new ExampleTask("First")));
		$queue->send(new Envelope(new ExampleTask("Second")));
		$queue->send(new Envelope(new ExampleTask("Third")));

		$manager = $this->createManager(["app" => $queue]);
		$listing = $manager->getQueuedTasksOfQueue("app", 2);

		self::assertTrue($listing->supportsListing);
		self::assertNull($listing->totalCount);
		self::assertTrue($listing->hasMore);
		self::assertSame(["First", "Second"], array_map(static fn ($task) => $task->label, $listing->tasks));

		$removed = $manager->removeQueuedTask("app:2");
		self::assertSame("Second", $removed->label);
		self::assertSame(
			["First", "Third"],
			array_map(static fn ($task) => $task->label, $manager->getQueuedTasksOfQueue("app")->tasks),
		);
	}

	/**
	 *
	 */
	public function testRemoveQueuedTask () : void
	{
		$queue = new ListableTestTransport([
			new Envelope(new ExampleTask("First"), [new TaskIdStamp("task-1")]),
			new Envelope(new ExampleTask("Second"), [new TaskIdStamp("task-2")]),
		]);
		$manager = $this->createManager(["app" => $queue]);

		$id = $manager->getQueuedTasksOfQueue("app")->tasks[1]->id;
		self::assertNotNull($id);

		// the id survives a round trip through a string, e.g. in a URL
		$removed = $manager->removeQueuedTask($id->toString());

		self::assertSame("Second", $removed->label);
		self::assertSame(1, $queue->getMessageCount());
		self::assertNull($manager->findQueuedTask($id));
		self::assertSame("First", $manager->findQueuedTask(new QueuedTaskId("app", "1"))?->label);
	}

	/**
	 *
	 */
	public function testCanNotRemoveStartedTask () : void
	{
		$queue = new ListableTestTransport([
			new Envelope(new ExampleTask("First"), [new TaskIdStamp("task-1")]),
		]);
		$this->startTask("task-1");

		$this->expectException(QueuedTaskAlreadyStartedException::class);

		try
		{
			$this->createManager(["app" => $queue])->removeQueuedTask("app:1");
		}
		finally
		{
			self::assertSame(1, $queue->getMessageCount());
		}
	}

	/**
	 * A task that failed and waits for its retry has only finished runs, so it is not started.
	 */
	public function testCanRemoveTaskWaitingForRetry () : void
	{
		$queue = new ListableTestTransport([
			new Envelope(new ExampleTask("First"), [new TaskIdStamp("task-1")]),
		]);
		$run = $this->startTask("task-1");
		$run->finish(false, "failed");
		$this->logModel->flush();

		$this->createManager(["app" => $queue])->removeQueuedTask("app:1");

		self::assertSame(0, $queue->getMessageCount());
	}

	/**
	 *
	 */
	public function testRemoveMissingTask () : void
	{
		$this->expectException(QueuedTaskNotFoundException::class);
		$this->createManager(["app" => ListableTestTransport::withMessages(1)])->removeQueuedTask("app:2");
	}

	/**
	 *
	 */
	public function testRemoveFromUnknownQueue () : void
	{
		$this->expectException(UnknownQueueException::class);
		$this->expectExceptionMessage("Unknown queue 'other'.");
		$this->createManager(["app" => ListableTestTransport::withMessages(1)])->removeQueuedTask("other:1");
	}

	/**
	 *
	 */
	public function testRemoveFromSyncQueue () : void
	{
		$this->expectException(UnknownQueueException::class);
		$this->expectExceptionMessage("'app' is no queue: uses a sync transport");
		$this->createManager(["app" => new SyncTransport(self::createStub(MessageBusInterface::class))])
			->removeQueuedTask("app:1");
	}

	/**
	 *
	 */
	public function testRemoveFromNonListableQueue () : void
	{
		$this->expectException(UnsupportedQueueOperationException::class);
		$this->createManager(["app" => self::createStub(TransportInterface::class)])->removeQueuedTask("app:1");
	}

	/**
	 * Creates a task run for the given task id, that is still running
	 */
	private function startTask (string $taskId) : TaskRun
	{
		$log = $this->logModel->getLogForUuid(new ExampleTask("Started"), $taskId);
		$run = $this->logModel->createRunForTask($log);
		$this->logModel->flush();

		return $run;
	}

	/**
	 * @param array<string, TransportInterface> $transports
	 * @param list<string>                      $failureTransports
	 */
	private function createManager (array $transports, array $failureTransports = []) : QueueManager
	{
		$helper = new TransportsHelper(
			new ServiceLocator(array_map(
				static fn (TransportInterface $transport) => static fn () => $transport,
				$transports,
			)),
			new BundleConfig([], $failureTransports),
			self::createStub(SendersLocatorInterface::class),
		);

		return new QueueManager($helper, $this->logModel);
	}
}
