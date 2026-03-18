<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Event;

use PHPUnit\Framework\TestCase;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Exception\Registry\DuplicateTaskRegisteredException;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class RegisterTasksEventTest extends TestCase
{
	// region Helpers

	// @phpstan-ignore-next-line 21torr.custom.task.suffix
	private function createTask (string $label, ?string $group = null) : Task
	{
		return new readonly class($label, $group) extends Task {
			public function __construct (
				private string $label,
				private ?string $group,
			)
			{
				parent::__construct();
			}

			#[\Override]
			public function getMetaData () : TaskMetaData
			{
				return new TaskMetaData($this->label, $this->group);
			}
		};
	}

	// endregion

	public function testRegisterAddsTask () : void
	{
		$task = $this->createTask("My Task");
		$event = new RegisterTasksEvent();
		$event->register($task);

		self::assertSame([$task], $event->getTasks());
	}

	public function testRegisterThrowsOnDuplicateKey () : void
	{
		$event = new RegisterTasksEvent();
		$event->register($this->createTask("My Task"));

		$this->expectException(DuplicateTaskRegisteredException::class);
		$event->register($this->createTask("My Task"));
	}

	public function testGetTasksReturnsSortedByLabel () : void
	{
		$event = new RegisterTasksEvent();
		$event->register($this->createTask("Zebra Task"));
		$event->register($this->createTask("Alpha Task"));
		$event->register($this->createTask("Middle Task"));

		$labels = array_map(
			static fn (Task $t) => $t->getMetaData()->label,
			$event->getTasks(),
		);

		self::assertSame(["Alpha Task", "Middle Task", "Zebra Task"], $labels);
	}

	public function testRegisterIsChainable () : void
	{
		$event = new RegisterTasksEvent();
		$result = $event->register($this->createTask("Task A"));

		self::assertSame($event, $result);
	}
}
