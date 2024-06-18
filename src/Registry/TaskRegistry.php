<?php declare(strict_types=1);

namespace Torr\TaskManager\Registry;

use Psr\EventDispatcher\EventDispatcherInterface;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Task\Task;

/**
 * Contains all tasks that are automatically configured to be runnable.
 */
final class TaskRegistry
{
	/** @var array<string, Task>|null */
	private ?array $tasks = null;

	public function __construct (
		private readonly EventDispatcherInterface $dispatcher,
	) {}

	/**
	 * Returns a list of all tasks, grouped by group label.
	 *
	 * @return array<string, Task[]>
	 *
	 * @api
	 */
	public function getGroupedTasks () : array
	{
		$grouped = [];
		$ungrouped = [];

		foreach ($this->fetchAllTasks() as $task)
		{
			$definition = $task->getMetaData();

			if (null !== $definition->group)
			{
				$grouped[$definition->group][] = $task;
			}
			else
			{
				$ungrouped[] = $task;
			}
		}

		// sort groups by group label
		uksort($grouped, "strnatcasecmp");

		if (!empty($ungrouped))
		{
			// append ungrouped
			$grouped["(other)"] = $ungrouped;
		}

		return $grouped;
	}

	/**
	 * @return Task[]
	 *
	 * @api
	 */
	public function getAllTasks () : array
	{
		return array_values($this->fetchAllTasks());
	}

	/**
	 * Returns a task by its key
	 *
	 * @api
	 */
	public function getTaskByKey (string $key) : ?Task
	{
		return $this->fetchAllTasks()[$key] ?? null;
	}

	/**
	 * @return array<string, Task>
	 */
	private function fetchAllTasks () : array
	{
		if (null !== $this->tasks)
		{
			return $this->tasks;
		}

		$event = new RegisterTasksEvent();
		$this->dispatcher->dispatch($event);
		$this->tasks = [];

		foreach ($event->getTasks() as $task)
		{
			$this->tasks[$task->getMetaData()->getKey()] = $task;
		}

		// sort tasks globally by name
		uasort(
			$this->tasks,
			static fn (Task $left, Task $right) => strnatcasecmp($left->getMetaData()->label, $right->getMetaData()->label),
		);

		return $this->tasks;
	}
}
