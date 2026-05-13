<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Normalizer;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Torr\TaskManager\Entity\TaskLog;
use Torr\TaskManager\Normalizer\TaskDetailsNormalizer;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskDetailsNormalizerTest extends TestCase
{
	// region Helpers

	private function createTask (string $label = "Test Task") : Task
	{
		// @phpstan-ignore-next-line 21torr.custom.task.suffix
		return new readonly class($label) extends Task {
			public function __construct (
				private string $label,
			)
			{
				parent::__construct();
			}

			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData($this->label);
			}
		};
	}

	private function createNormalizer (
		?SerializerInterface $serializer = null,
		?LoggerInterface $logger = null,
	) : TaskDetailsNormalizer
	{
		return new TaskDetailsNormalizer(
			$serializer ?? self::createStub(SerializerInterface::class),
			$logger ?? self::createStub(LoggerInterface::class),
		);
	}

	// endregion

	// region normalizeTaskDetails

	public function testNormalizeIncludesLabelAndSerializedTask () : void
	{
		$task = $this->createTask("My Label");
		$serializer = $this->createMock(SerializerInterface::class);
		$serializer
			->expects(self::once())
			->method("serialize")
			->with($task, JsonEncoder::FORMAT)
			->willReturn('{"ulid":"abc"}');

		$details = $this->createNormalizer($serializer)->normalizeTaskDetails(new Envelope($task));

		self::assertArrayHasKey("label", $details);
		self::assertSame("My Label", $details["label"]);

		self::assertArrayHasKey("task", $details);
		self::assertSame('{"ulid":"abc"}', $details["task"]);
	}

	public function testNormalizeExtractsTransportAndHandledBy () : void
	{
		$task = $this->createTask();
		$envelope = new Envelope($task, [
			new ReceivedStamp("my_transport"),
			new HandledStamp("result", "App\\Handler::__invoke"),
		]);

		$details = $this->createNormalizer()->normalizeTaskDetails($envelope);

		self::assertArrayHasKey("transport", $details);
		self::assertSame("my_transport", $details["transport"]);

		self::assertArrayHasKey("handledBy", $details);
		self::assertSame("App\\Handler::__invoke", $details["handledBy"]);
	}

	public function testNormalizeWithoutStampsHasNullTransportAndHandledBy () : void
	{
		$task = $this->createTask();
		$details = $this->createNormalizer()->normalizeTaskDetails(new Envelope($task));

		self::assertArrayHasKey("transport", $details);
		self::assertNull($details["transport"]);

		self::assertArrayHasKey("handledBy", $details);
		self::assertNull($details["handledBy"]);
	}

	public function testNormalizeNonTaskMessageSkipsLabelAndTask () : void
	{
		$message = new \stdClass();
		$serializer = $this->createMock(SerializerInterface::class);
		$serializer->expects(self::never())->method("serialize");

		$details = $this->createNormalizer($serializer)->normalizeTaskDetails(new Envelope($message));

		self::assertArrayNotHasKey("label", $details);
		self::assertArrayNotHasKey("task", $details);
	}

	// endregion

	// region deserializeTask

	public function testDeserializeReturnsNullWhenNoTaskStored () : void
	{
		$task = $this->createTask();
		$log = new TaskLog($task);
		// taskDetails is empty by default

		$result = $this->createNormalizer()->deserializeTask($log);

		self::assertNull($result);
	}

	public function testDeserializeReturnsTask () : void
	{
		$originalTask = $this->createTask();
		$log = new TaskLog($originalTask);
		$log->setTaskDetails(["task" => '{"ulid":"abc"}']);

		$serializer = $this->createMock(SerializerInterface::class);
		$serializer
			->expects(self::once())
			->method("deserialize")
			->with('{"ulid":"abc"}', $log->taskClass, JsonEncoder::FORMAT)
			->willReturn($originalTask);

		$result = $this->createNormalizer($serializer)->deserializeTask($log);

		self::assertSame($originalTask, $result);
	}

	public function testDeserializeReturnsNullWhenDeserializedObjectIsNotATask () : void
	{
		$task = $this->createTask();
		$log = new TaskLog($task);
		$log->setTaskDetails(["task" => "{}'"]);

		$serializer = self::createStub(SerializerInterface::class);
		$serializer->method("deserialize")->willReturn(new \stdClass());

		$result = $this->createNormalizer($serializer)->deserializeTask($log);

		self::assertNull($result);
	}

	public function testDeserializeLogsErrorAndReturnsNullOnSerializerException () : void
	{
		$task = $this->createTask();
		$log = new TaskLog($task);
		$log->setTaskDetails(["task" => "invalid-json"]);

		$serializer = self::createStub(SerializerInterface::class);
		$serializer->method("deserialize")->willThrowException(new NotEncodableValueException("bad json"));

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method("error");

		$result = $this->createNormalizer($serializer, $logger)->deserializeTask($log);

		self::assertNull($result);
	}

	// endregion
}
