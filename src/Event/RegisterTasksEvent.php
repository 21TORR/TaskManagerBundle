<?php declare(strict_types=1);

namespace Torr\TaskManager\Event;

use Torr\TaskManager\Exception\Registry\DuplicateTaskRegisteredException;
use Torr\TaskManager\Registry\Task\RegisteredTask;
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
	/** @var array<string, RegisteredTask> */
	public array $tasks = [];

	/**
	 * Registers a task
	 *
	 * @param bool $public whether this task is public (= everywhere registrable) or private (= only registrable via CLI)
	 *
	 * @throws DuplicateTaskRegisteredException
	 */
	public function register (
		Task $task,
		bool $public = true,
	) : self
	{
		$definition = $task->getMetaData();
		$key = $definition->getKey();

		if (\array_key_exists($key, $this->tasks))
		{
			throw new DuplicateTaskRegisteredException(sprintf(
				"Duplicate task registered with key '%s'",
				$key,
			));
		}

		$this->tasks[$key] = new RegisteredTask($task, $public);

		return $this;
	}

	/**
	 * @return list<RegisteredTask>
	 */
	public function getTasks () : array
	{
		return  array_values($this->tasks);
	}
}
