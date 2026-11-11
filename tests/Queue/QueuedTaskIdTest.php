<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Queue;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Exception\Queue\InvalidQueuedTaskIdException;
use Torr\TaskManager\Queue\QueuedTaskId;

/**
 * @internal
 */
final class QueuedTaskIdTest extends TestCase
{
	/**
	 *
	 */
	public static function provideRoundTrip () : iterable
	{
		yield "doctrine" => ["app", "123", "app:123"];
		yield "redis" => ["app_urgent", "1700000000000-0", "app_urgent:1700000000000-0"];
		yield "separator in parts" => ["a:b", "c:d", "a%3Ab:c%3Ad"];
		yield "url unsafe characters" => ["a/b c", "ä?", "a%2Fb%20c:%C3%A4%3F"];
	}

	/**
	 *
	 */
	#[DataProvider("provideRoundTrip")]
	public function testRoundTrip (string $queue, string $transportMessageId, string $expected) : void
	{
		$id = new QueuedTaskId($queue, $transportMessageId);
		self::assertSame($expected, $id->toString());
		self::assertSame($expected, (string) $id);

		$parsed = QueuedTaskId::fromString($expected);
		self::assertSame($queue, $parsed->queue);
		self::assertSame($transportMessageId, $parsed->transportMessageId);
	}

	/**
	 *
	 */
	public static function provideInvalid () : iterable
	{
		yield "empty" => [""];
		yield "no separator" => ["app"];
		yield "too many separators" => ["app:1:2"];
		yield "empty queue" => [":1"];
		yield "empty id" => ["app:"];
	}

	/**
	 *
	 */
	#[DataProvider("provideInvalid")]
	public function testInvalid (string $id) : void
	{
		$this->expectException(InvalidQueuedTaskIdException::class);
		QueuedTaskId::fromString($id);
	}
}
