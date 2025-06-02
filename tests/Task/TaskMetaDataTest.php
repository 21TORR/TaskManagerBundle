<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Task;

use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Exception\Task\InvalidTaskDefinitionException;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskMetaDataTest extends TestCase
{
	public static function provideValidUniqueTaskIds () : iterable
	{
		yield "plain" => ["test"];
		yield "with dash" => ["a-b"];
		yield "with underscore" => ["a-b_c"];
		yield "all characters" => ["a-b_c.d"];
		yield "numbers" => ["5"];
		yield "numbers longer" => ["1-2-3-4"];
	}

	/**
	 * @dataProvider provideValidUniqueTaskIds
	 */
	public function testValidUniqueTaskIds (string $uniqueTaskId) : void
	{
		$metadata = new TaskMetaData("Test", uniqueTaskId: $uniqueTaskId);
		self::assertSame($uniqueTaskId, $metadata->getKey());
	}

	public static function provideInvalidUniqueTaskIds () : iterable
	{
		yield "empty" => [""];
		yield "dash at the end" => ["test-"];
		yield "dot at the end" => ["test."];
		yield "underscore at the end" => ["test_"];
		yield "dash at the beginning" => ["-test"];
		yield "dot at the beginning" => ["_test"];
		yield "underscore at the beginning" => [".test"];
		yield "double dash" => ["a--b"];
		yield "special characters" => ["a@b"];
		yield "upper case characters" => ["aBc"];
	}

	/**
	 * @dataProvider provideInvalidUniqueTaskIds
	 */
	public function testInvalidUniqueTaskIds (string $uniqueTaskId) : void
	{
		$this->expectException(InvalidTaskDefinitionException::class);
		$this->expectExceptionMessage(\sprintf(
			"Invalid unique task id: '%s'",
			$uniqueTaskId,
		));

		new TaskMetaData("Test", uniqueTaskId: $uniqueTaskId);
	}
}
