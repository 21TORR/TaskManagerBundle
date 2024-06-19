<?php declare(strict_types=1);

namespace Torr\TaskManager\Registry;

use Psr\EventDispatcher\EventDispatcherInterface;
use Torr\TaskManager\Event\RegisterTasksEvent;
use Torr\TaskManager\Registry\Task\RegisteredTask;
use Torr\TaskManager\Task\Task;

/**
 * Contains all tasks that are automatically configured to be runnable.
 */
final class TaskRegistry
{
	public const bool ONLY_PUBLIC_TASKS = true;
	public const bool INCLUDE_PRIVATE_TASKS = false;

	/** @var array<string, RegisteredTask>|null */
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
	public function getGroupedTasks (bool $onlyPublic = self::ONLY_PUBLIC_TASKS) : array
	{
		$grouped = [];
		$ungrouped = [];
		$registeredTasks = $this->fetchAllTasks();

		if ($onlyPublic)
		{
			$registeredTasks = array_filter(
				$registeredTasks,
				static fn (RegisteredTask $task) => $task->public,
			);
		}

		foreach ($registeredTasks as $registeredTask)
		{
			$task = $registeredTask->task;
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
		$tasks = [];

		foreach ($this->fetchAllTasks() as $registeredTask)
		{
			$tasks[] = $registeredTask->task;
		}

		return $tasks;
	}

	/**
	 * Returns a task by its key
	 *
	 * @api
	 */
	public function getTaskByKey (string $key) : ?Task
	{
		$registeredTask = $this->fetchAllTasks()[$key] ?? null;

		return $registeredTask?->task;
	}

	/**
	 *
	 */
	public function isPublicTask (string $key) : bool
	{
		$registeredTask = $this->fetchAllTasks()[$key] ?? null;

		return $registeredTask?->public ?? true;
	}

	/**
	 * @return array<string, RegisteredTask>
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
			$this->tasks[$task->task->getMetaData()->getKey()] = $task;
		}

		// sort tasks globally by name
		uasort(
			$this->tasks,
			static fn (RegisteredTask $left, RegisteredTask $right) => strnatcasecmp($left->task->getMetaData()->label, $right->task->getMetaData()->label),
		);

		return $this->tasks;
	}
}
