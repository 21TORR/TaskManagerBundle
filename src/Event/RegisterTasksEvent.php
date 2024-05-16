<?php declare(strict_types=1);

namespace Torr\TaskManager\Event;

use Torr\TaskManager\Exception\Registry\DuplicateTaskRegisteredException;
use Torr\TaskManager\Task\Task;

/**
 * This event lets you register your tasks, so that the UI can make them selectable
 * and runnable.
 *
 * You can always register tasks yourself, this event is the integration into the automated
 * tools of this bundle.
 */
final class RegisterTasksEvent
{
	/** @var array<string, Task> */
	public array $tasks = [];


	/**
	 * Registers a task
	 *
	 * @throws DuplicateTaskRegisteredException
	 */
	public function register (Task $task) : self
	{
		$definition = $task->getMetaData();
		$key = $definition->getKey();

		if (\array_key_exists($key, $this->tasks))
		{
			throw new DuplicateTaskRegisteredException(\sprintf(
				"Duplicate task registered with key '%s'",
				$key,
			));
		}

		$this->tasks[$key] = $task;
		return $this;
	}


	/**
	 * @return Task[]
	 */
	public function getTasks () : array
	{
		$entries = \array_values($this->tasks);

		\usort(
			$entries,
			static fn (Task $left, Task $right) => \strnatcasecmp($left->getMetaData()->label, $right->getMetaData()->label),
		);

		return $entries;
	}
}
