<?php declare(strict_types=1);

namespace Torr\TaskManager\Event;

/**
 * Dispatched after queues were cleared via the `task-manager:clear-queue` command.
 */
final readonly class QueuesClearedViaCliEvent
{
	/**
	 * @param array<string, int> $removedTasks the number of removed tasks, indexed by queue name
	 */
	public function __construct (
		public array $removedTasks,
	) {}
}
