<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Identification;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\Serialization\Serializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Tests\Torr\TaskManager\Identification\Fixtures\ExampleMessage;
use Torr\TaskManager\Identification\TaskIdStamp;

/**
 * @internal
 */
final class TaskIdStampTest extends TestCase
{
	/**
	 */
	public function testGeneratesUniqueTaskIds () : void
	{
		self::assertNotSame(new TaskIdStamp()->taskId, new TaskIdStamp()->taskId);
	}

	/**
	 */
	public function testKeepsGivenTaskId () : void
	{
		self::assertSame("some-task-id", new TaskIdStamp("some-task-id")->taskId);
	}

	/**
	 */
	public static function provideTaskIdSurvivesTransportSerialization () : iterable
	{
		yield "symfony serializer" => [Serializer::create()];
		yield "php serializer" => [new PhpSerializer()];
	}

	/**
	 * The task id must survive the transport, otherwise the worker creates a second task log entry
	 */
	#[DataProvider("provideTaskIdSurvivesTransportSerialization")]
	public function testTaskIdSurvivesTransportSerialization (SerializerInterface $serializer) : void
	{
		$stamp = new TaskIdStamp();
		$envelope = $serializer->decode($serializer->encode(new Envelope(new ExampleMessage(), [$stamp])));

		self::assertSame($stamp->taskId, $envelope->last(TaskIdStamp::class)?->taskId);
	}
}
