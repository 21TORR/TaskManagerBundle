<?php declare(strict_types=1);

namespace Tests\Torr\TaskManager\Registry;

use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Registry\TaskRegistry;
use Torr\TaskManager\Task\Task;
use Torr\TaskManager\Task\TaskMetaData;

/**
 * @internal
 */
final class TaskRegistryTest extends TestCase
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

	/**
	 * @param Task[] $tasks
	 */
	private function createRegistry (array $tasks) : TaskRegistry
	{
		$dispatcher = $this->createStub(EventDispatcherInterface::class);
		$dispatcher->method("dispatch")->willReturnCallback(
			static function (RegisterTasksEvent $event) use ($tasks) : RegisterTasksEvent
			{
				foreach ($tasks as $task)
				{
					$event->register($task);
				}

				return $event;
			},
		);

		return new TaskRegistry($dispatcher);
	}

	// endregion

	public function testGetAllTasksReturnsRegisteredTasks () : void
	{
		$task1 = $this->createTask("Task A");
		$task2 = $this->createTask("Task B");
		$registry = $this->createRegistry([$task1, $task2]);

		self::assertSame([$task1, $task2], $registry->getAllTasks());
	}

	public function testGetAllTasksReturnsSortedByLabel () : void
	{
		$registry = $this->createRegistry([
			$this->createTask("Zebra"),
			$this->createTask("Alpha"),
			$this->createTask("Middle"),
		]);

		$labels = array_map(
			static fn (Task $t) => $t->getMetaData()->label,
			$registry->getAllTasks(),
		);

		self::assertSame(["Alpha", "Middle", "Zebra"], $labels);
	}

	public function testGetTaskByKeyReturnsTask () : void
	{
		$task = $this->createTask("My Task");
		$registry = $this->createRegistry([$task]);

		$key = $task->getMetaData()->getKey();
		self::assertSame($task, $registry->getTaskByKey($key));
	}

	public function testGetTaskByKeyReturnsNullWhenNotFound () : void
	{
		$registry = $this->createRegistry([]);

		self::assertNull($registry->getTaskByKey("non-existent-key"));
	}

	public function testGetGroupedTasksSeparatesGroups () : void
	{
		$registry = $this->createRegistry([
			$this->createTask("Task A", "Group One"),
			$this->createTask("Task B", "Group Two"),
			$this->createTask("Task C", "Group One"),
		]);

		$grouped = $registry->getGroupedTasks();

		self::assertArrayHasKey("Group One", $grouped);
		self::assertArrayHasKey("Group Two", $grouped);
		self::assertCount(2, $grouped["Group One"]);
		self::assertCount(1, $grouped["Group Two"]);
	}

	public function testGetGroupedTasksPutsUngroupedInOther () : void
	{
		$registry = $this->createRegistry([
			$this->createTask("Grouped Task", "My Group"),
			$this->createTask("Ungrouped Task"),
		]);

		$grouped = $registry->getGroupedTasks();

		self::assertArrayHasKey("(other)", $grouped);
		self::assertCount(1, $grouped["(other)"]);
	}

	public function testGetGroupedTasksSortsGroupsAlphabetically () : void
	{
		$registry = $this->createRegistry([
			$this->createTask("Task Z", "Zebra"),
			$this->createTask("Task A", "Alpha"),
			$this->createTask("Task M", "Middle"),
		]);

		$groupKeys = array_keys($registry->getGroupedTasks());

		self::assertSame(["Alpha", "Middle", "Zebra"], $groupKeys);
	}

	public function testDispatcherCalledOnlyOnce () : void
	{
		$dispatcher = $this->createMock(EventDispatcherInterface::class);
		$dispatcher->expects(self::once())
			->method("dispatch")
			->willReturnCallback(static fn (RegisterTasksEvent $event) : RegisterTasksEvent => $event);

		$registry = new TaskRegistry($dispatcher);

		// Call multiple methods — dispatcher must only be invoked once
		$registry->getAllTasks();
		$registry->getAllTasks();
		$registry->getTaskByKey("any");
		$registry->getGroupedTasks();
	}
}
