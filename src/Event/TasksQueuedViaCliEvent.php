<?php declare(strict_types=1);

namespace Torr\TaskManager\Event;

use Torr\TaskManager\Task\Task;

/**
 * Dispatched after tasks were queued via the `task-manager:queue` command.
 */
final readonly class TasksQueuedViaCliEvent
{
	/**
	 * @param list<Task> $tasks
	 */
	public function __construct (
		public array $tasks,
	) {}
}
