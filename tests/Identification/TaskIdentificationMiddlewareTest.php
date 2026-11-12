<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Identification;

use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Handler\HandlerDescriptor;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Tests\Torr\TaskManager\Fixtures\ExampleTask;
use Tests\Torr\TaskManager\Fixtures\TestEntityManagerFactory;
use Tests\Torr\TaskManager\Identification\Fixtures\ExampleMessage;
use Torr\TaskManager\Director\ActiveRunRegistry;
use Torr\TaskManager\Director\TaskDirector;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Entity\TaskRun;
use Torr\TaskManager\Identification\TaskIdentificationMiddleware;
use Torr\TaskManager\Identification\TaskIdStamp;
use Torr\TaskManager\Model\TaskLogModel;
use Torr\TaskManager\Normalizer\TaskDetailsNormalizer;

/**
 * @internal
 */
final class TaskIdentificationMiddlewareTest extends TestCase
{
	private const string TRANSPORT_ASYNC = "async";
	private const string TRANSPORT_SYNC = "sync";
	private EntityManager $entityManager;
	private TaskLogModel $logModel;
	private TaskDirector $taskDirector;
	private ActiveRunRegistry $activeRuns;

	/**
	 *
	 */
	#[\Override]
	protected function setUp () : void
	{
		$this->entityManager = TestEntityManagerFactory::create();
		$this->logModel = new TaskLogModel($this->entityManager, new NullLogger());
		$this->activeRuns = new ActiveRunRegistry();
		$this->taskDirector = new TaskDirector($this->activeRuns, new NullLogger(), new NullOutput());
	}

	// region Helpers
	/**
	 * @param list<callable|HandlerDescriptor> $handlers
	 * @param self::TRANSPORT_*|null           $transport the transport the message is routed to (null = not routed)
	 */
	private function createBus (object $message, array $handlers, ?string $transport = null) : MessageBus
	{
		$handlersLocator = new HandlersLocator([$message::class => $handlers]);
		$sendersLocator = new class() implements SendersLocatorInterface {
			/** @var array<string, SenderInterface> */
			public array $senders = [];

			#[\Override]
			public function getSenders (Envelope $envelope) : iterable
			{
				return $this->senders;
			}
		};

		$bus = new MessageBus([
			new TaskIdentificationMiddleware(
				$this->logModel,
				new TaskDetailsNormalizer(
					new Serializer([new ObjectNormalizer()], [new JsonEncoder()]),
					new NullLogger(),
				),
				$handlersLocator,
				$sendersLocator,
				$this->activeRuns,
				new NullLogger(),
			),
			new SendMessageMiddleware($sendersLocator),
			new HandleMessageMiddleware($handlersLocator),
		]);

		if (null !== $transport)
		{
			$sendersLocator->senders = [
				$transport => self::TRANSPORT_SYNC === $transport
					? new SyncTransport($bus)
					: new InMemoryTransport(),
			];
		}

		return $bus;
	}

	/**
	 * Dispatches the message like a worker would do it
	 */
	private function handleInWorker (MessageBus $bus, object $message, ?string $taskId = null) : void
	{
		$stamps = [new ReceivedStamp(self::TRANSPORT_ASYNC)];

		if (null !== $taskId)
		{
			$stamps[] = new TaskIdStamp($taskId);
		}

		$bus->dispatch(new Envelope($message, $stamps));
	}

	/**
	 * Fetches the single log entry fresh from the database
	 */
	private function fetchLog () : TaskLog
	{
		$this->entityManager->clear();
		$logs = $this->entityManager->getRepository(TaskLog::class)->findAll();
		self::assertCount(1, $logs);

		return $logs[0];
	}

	/**
	 * @return list<TaskRun>
	 */
	private function fetchRuns () : array
	{
		return array_values($this->fetchLog()->runs->toArray());
	}

	private function successfulTaskHandler () : callable
	{
		return function (ExampleTask $task) : void
		{
			$this->taskDirector->startRun($task)->finish(true);
		};
	}

	// endregion

	public function testQueuedMessageHasLogButNoRun () : void
	{
		$message = new ExampleMessage();
		$handled = false;
		$bus = $this->createBus($message, [static function () use (&$handled) : void { $handled = true; }], self::TRANSPORT_ASYNC);

		$bus->dispatch($message);

		self::assertFalse($handled);
		$log = $this->fetchLog();
		self::assertCount(0, $log->runs);
		self::assertNull($log->getStatus());
	}

	public function testMessageInWorkerTracksDuration () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static function () : string
		{
			usleep(20_000);

			return "done";
		}]);

		$this->handleInWorker($bus, $message);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertSame("done", $runs[0]->output);
		self::assertGreaterThanOrEqual(20_000_000, $runs[0]->duration);
		self::assertSame(self::TRANSPORT_ASYNC, $this->fetchLog()->getTransport());
	}

	public function testNotRoutedMessageTracksDuration () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static function () : void { usleep(20_000); }]);

		$bus->dispatch($message);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertGreaterThanOrEqual(20_000_000, $runs[0]->duration);
	}

	public function testFailingMessageInWorker () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static function () : never { throw new \RuntimeException("boom"); }]);

		try
		{
			$this->handleInWorker($bus, $message);
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
		self::assertStringContainsString("boom", (string) $runs[0]->output);
	}

	public function testSyncTransportCreatesSingleRunOnSuccess () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static fn () => "done"], self::TRANSPORT_SYNC);

		$bus->dispatch($message);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertSame("done", $runs[0]->output);
	}

	public function testSyncTransportCreatesSingleRunOnFailure () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static function () : never { throw new \RuntimeException("boom"); }], self::TRANSPORT_SYNC);

		try
		{
			$bus->dispatch($message);
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
	}

	public function testTaskFinishedByDirector () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [function (ExampleTask $task) : void
		{
			$run = $this->taskDirector->startRun($task);
			$run->io->writeln("important output");
			$run->finish(true);
		}]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertTrue($runs[0]->hasFinishedProperly());
		self::assertSame("important output", $runs[0]->output);
	}

	public function testTaskReportingFailureWithoutException () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [function (ExampleTask $task) : void
		{
			$this->taskDirector->startRun($task)->finish(false);
		}]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
		self::assertTrue($runs[0]->hasFinishedProperly());
	}

	public function testTaskWithoutDirectorStillHasRun () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [static function () : void {}]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertFalse($runs[0]->hasFinishedProperly());
	}

	public function testTaskFailingBeforeStartRunHasFailedRun () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [static function () : never { throw new \RuntimeException("boom"); }]);

		try
		{
			$this->handleInWorker($bus, $task);
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
	}

	public function testTaskOutputIsKeptOnException () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [function (ExampleTask $task) : never
		{
			$this->taskDirector->startRun($task)->io->writeln("important output");

			throw new \RuntimeException("boom");
		}]);

		try
		{
			$this->handleInWorker($bus, $task);
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
		self::assertFalse($runs[0]->hasFinishedProperly());
		self::assertStringContainsString("important output", (string) $runs[0]->output);
		self::assertStringContainsString("boom", (string) $runs[0]->output);
	}

	public function testTaskOutputIsKeptIfFinishIsForgotten () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [function (ExampleTask $task) : void
		{
			$this->taskDirector->startRun($task)->io->writeln("important output");
		}]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertFalse($runs[0]->hasFinishedProperly());
		self::assertSame("important output", $runs[0]->output);
	}

	public function testRunIsFinishedIfHandlerClearsEntityManager () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [function (ExampleTask $task) : void
		{
			$run = $this->taskDirector->startRun($task);
			$this->entityManager->clear();
			$run->finish(true);
		}]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->isFinished);
		self::assertTrue($runs[0]->success);
	}

	public function testMultipleHandlersShareTheRun () : void
	{
		$task = new ExampleTask("Test");
		$bus = $this->createBus($task, [
			new HandlerDescriptor($this->successfulTaskHandler(), ["alias" => "first"]),
			new HandlerDescriptor($this->successfulTaskHandler(), ["alias" => "second"]),
		]);

		$this->handleInWorker($bus, $task);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertTrue($runs[0]->success);
		self::assertTrue($runs[0]->hasFinishedProperly());
	}

	public function testRetryAfterCrashKeepsCrashedRunAsFailed () : void
	{
		$task = new ExampleTask("Test");

		// a previous try, that crashed while handling
		$this->logModel->createRunForTask($this->logModel->getLogForUuid($task, "my-task-id"));
		$this->logModel->flush();
		$this->entityManager->clear();

		$bus = $this->createBus($task, [$this->successfulTaskHandler()]);
		$this->handleInWorker($bus, $task, "my-task-id");

		$runs = $this->fetchRuns();
		self::assertCount(2, $runs);
		self::assertFalse($runs[0]->success);
		self::assertSame(TaskRun::CRASHED_OUTPUT, $runs[0]->output);
		self::assertTrue($runs[1]->success);
	}

	public function testFailingRetryAfterCrashFinishesBothRuns () : void
	{
		$task = new ExampleTask("Test");

		$this->logModel->createRunForTask($this->logModel->getLogForUuid($task, "my-task-id"));
		$this->logModel->flush();
		$this->entityManager->clear();

		$bus = $this->createBus($task, [static function () : never { throw new \RuntimeException("boom"); }]);

		try
		{
			$this->handleInWorker($bus, $task, "my-task-id");
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(2, $runs);
		self::assertSame(TaskRun::CRASHED_OUTPUT, $runs[0]->output);
		self::assertTrue($runs[1]->isFinished);
		self::assertFalse($runs[1]->success);
		self::assertStringContainsString("boom", (string) $runs[1]->output);
	}

	public function testFailedRetryDoesNotContainResultOfPreviousTry () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static function () : never { throw new \RuntimeException("boom"); }]);

		try
		{
			$bus->dispatch(new Envelope($message, [
				new ReceivedStamp(self::TRANSPORT_ASYNC),
				new HandledStamp("result of previous try", "other-handler"),
			]));
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertStringNotContainsString("result of previous try", (string) $runs[0]->output);
		self::assertStringContainsString("boom", (string) $runs[0]->output);
	}

	public function testResultsOfAllHandlersAreCombined () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [
			new HandlerDescriptor(static fn () => "first result", ["alias" => "first"]),
			new HandlerDescriptor(static fn () => "second result", ["alias" => "second"]),
		]);

		$this->handleInWorker($bus, $message);

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertSame("first result\n\nsecond result", $runs[0]->output);
	}

	public function testResultOfSuccessfulHandlerIsKeptIfAnotherHandlerFails () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [
			new HandlerDescriptor(static fn () => "first result", ["alias" => "first"]),
			new HandlerDescriptor(static function () : never { throw new \RuntimeException("boom"); }, ["alias" => "second"]),
		]);

		try
		{
			$this->handleInWorker($bus, $message);
			self::fail("Should have thrown");
		}
		catch (HandlerFailedException)
		{
		}

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertFalse($runs[0]->success);
		self::assertStringStartsWith("first result\n\n", (string) $runs[0]->output);
		self::assertStringContainsString("boom", (string) $runs[0]->output);
	}

	public function testSuccessfulRetryDoesNotContainResultOfPreviousTry () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static fn () => "new result"]);

		$bus->dispatch(new Envelope($message, [
			new ReceivedStamp(self::TRANSPORT_ASYNC),
			new HandledStamp("result of previous try", "other-handler"),
		]));

		$runs = $this->fetchRuns();
		self::assertCount(1, $runs);
		self::assertSame("new result", $runs[0]->output);
	}

	public function testRedeliveryAfterSuccessDoesNotFail () : void
	{
		$message = new ExampleMessage();
		$bus = $this->createBus($message, [static fn () => "done"]);

		$this->handleInWorker($bus, $message, "my-task-id");
		$this->entityManager->clear();
		$this->handleInWorker($bus, $message, "my-task-id");

		self::assertCount(1, $this->fetchRuns());
	}
}
