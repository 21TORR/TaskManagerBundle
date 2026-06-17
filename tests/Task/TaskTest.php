<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Task;

use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskTest extends TestCase
{
	/**
	 *
	 */
	public function testNewTaskUlid () : void
	{
		// @phpstan-ignore-next-line 21torr.custom.task.suffix
		$task = new readonly class() extends Task {
			/**
			 *
			 */
			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData("Test");
			}
		};

		/** @phpstan-ignore-next-line property.deprecated (The uuid integration will be refactored in v4) */
		$initialUlid = $task->ulid;
		$newTask = $task->withNewTaskUlid();
		/** @phpstan-ignore-next-line property.deprecated (The uuid integration will be refactored in v4) */
		self::assertNotSame($initialUlid, $newTask->ulid, "Task ULID should change on PHP 8.4");
	}
}
