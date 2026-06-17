<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Manager;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DeduplicateStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Torr\TaskManager\Config\BundleConfig;
use Torr\TaskManager\Manager\TaskManager;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;
use Torr\TaskManager\Transport\TransportsHelper;

/**
 * @internal
 */
final class TaskManagerTest extends TestCase
{
	// region Helpers
	private function createTask (?string $uniqueTaskId = null) : Task
	{
		// @phpstan-ignore-next-line 21torr.custom.task.suffix
		return new readonly class($uniqueTaskId) extends Task {
			public function __construct (
				private ?string $uniqueTaskId,
			)
			{
				parent::__construct();
			}

			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData("Test Task", uniqueTaskId: $this->uniqueTaskId);
			}
		};
	}

	/**
	 * Creates a listable transport backed by the given envelopes.
	 *
	 * @param Envelope[] $envelopes
	 */
	private function createListableTransport (array $envelopes = []) : ListableReceiverInterface|TransportInterface
	{
		return new class($envelopes) implements TransportInterface, ListableReceiverInterface {
			/** @param Envelope[] $envelopes */
			public function __construct (
				private readonly array $envelopes,
			) {}

			public function all (?int $limit = null) : iterable { return $this->envelopes; }

			public function find (mixed $id) : Envelope { throw new \RuntimeException("Not implemented"); }

			public function get () : iterable { return []; }

			public function ack (Envelope $envelope) : void {}

			public function reject (Envelope $envelope) : void {}

			public function send (Envelope $envelope) : Envelope { return $envelope; }
		};
	}

	/**
	 * @param array<string, TransportInterface> $transports
	 */
	private function createManager (array $transports, MessageBusInterface $bus) : TaskManager
	{
		$factories = [];

		foreach ($transports as $name => $transport)
		{
			$factories[$name] = static fn () => $transport;
		}

		$locator = new ServiceLocator($factories);
		$config = new BundleConfig(sortedQueues: array_keys($transports));
		$helper = new TransportsHelper($locator, $config);

		return new TaskManager($helper, $bus);
	}

	// endregion

	public function testEnqueueWithoutUniqueTaskIdAlwaysDispatches () : void
	{
		$bus = $this->createMock(MessageBusInterface::class);
		$bus->expects(self::once())->method("dispatch")->willReturnArgument(0);

		$manager = $this->createManager(["queue" => $this->createListableTransport()], $bus);
		$task = $this->createTask(null);

		self::assertIsString($manager->enqueue($task));
	}

	public function testEnqueueReturnsTrueWhenNoConflict () : void
	{
		$bus = self::createStub(MessageBusInterface::class);
		$bus->method("dispatch")->willReturnArgument(0);

		$manager = $this->createManager(["queue" => $this->createListableTransport()], $bus);

		self::assertIsString($manager->enqueue($this->createTask("test.task")));
	}

	public function testEnqueueReturnsFalseWhenDuplicateInQueue () : void
	{
		$bus = self::createMock(MessageBusInterface::class);

		$bus->expects(self::once())
			->method("dispatch")
			->with(self::callback(
				static fn (Envelope $envelope) : bool => "unique.key" === $envelope->last(DeduplicateStamp::class)?->getKey()->__toString(),
			))
			->willReturnArgument(0);

		$manager = $this->createManager(["queue" => $this->createListableTransport()], $bus);
		$manager->enqueue($this->createTask("unique.key"));
	}

	public function testEnqueueForwardsStampsToDispatchedEnvelope () : void
	{
		$capturedEnvelope = null;

		$bus = self::createStub(MessageBusInterface::class);
		$bus->method("dispatch")->willReturnCallback(
			static function (Envelope $envelope) use (&$capturedEnvelope) : Envelope
			{
				$capturedEnvelope = $envelope;

				return $envelope;
			},
		);

		$stamp = new class() implements StampInterface {};

		$manager = $this->createManager(["queue" => $this->createListableTransport()], $bus);
		$manager->enqueue($this->createTask(null), [$stamp]);

		self::assertNotNull($capturedEnvelope);
		self::assertNotEmpty($capturedEnvelope->all($stamp::class));
	}
}
