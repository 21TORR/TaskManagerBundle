<?php declare(strict_types=1);

namespace Torr\TaskManager\Registry;

use Psr\EventDispatcher\EventDispatcherInterface;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Registry\Data\Task;

/**
 * Contains all tasks that are automatically configured to be runnable.
 */
final class TaskRegistry
{
	/** @var array<string, Task[]>|null */
	private ?array $tasks = null;
	/** @var array<string, Task>|null */
	private ?array $keyMap = null;

	public function __construct (
		private readonly EventDispatcherInterface $dispatcher,
	) {}


	/**
	 * @return array<string, Task[]>
	 */
	public function getGroupedTasks () : array
	{
		return $this->tasks ??= $this->fetchGroupedTasks();
	}


	/**
	 * Returns a task by its key
	 */
	public function getTaskByKey (string $key) : ?Task
	{
		// be sure to fetch tasks
		$this->getGroupedTasks();

		return $this->keyMap[$key] ?? null;
	}


	/**
	 * Returns a list of all tasks, grouped by group label.
	 *
	 * @return array<string, Task[]>
	 */
	private function fetchGroupedTasks () : array
	{
		$event = new RegisterTasksEvent();
		$this->dispatcher->dispatch($event);
		$tasks = $event->getTasks();

		$this->keyMap = [];
		$grouped = [];
		$ungrouped = [];

		foreach ($tasks as $task)
		{
			if (null !== $task->group)
			{
				$grouped[$task->group][] = $task;
			}
			else
			{
				$ungrouped[] = $task;
			}

			$this->keyMap[$task->key] = $task;
		}

		// sort groups by group label
		\uksort($grouped, "strnatcasecmp");

		if (!empty($ungrouped))
		{
			// append ungrouped
			$grouped["(other)"] = $ungrouped;
		}

		// sort every group
		foreach ($grouped as &$entries)
		{
			\usort(
				$entries,
				static fn (Task $left, Task $right) => \strnatcasecmp($left->label, $right->label),
			);
		}

		return $grouped;
	}
}
