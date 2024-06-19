<?php declare(strict_types=1);

namespace Torr\TaskManager\Registry\Task;

use Torr\TaskManager\Task\Task;

/**
 * All info for a registered task
 *
 * @internal
 */
final readonly class RegisteredTask
{
	public function __construct (
		public Task $task,
		/**
		 * Whether this task is public or private. Private tasks are normally only visible on the CLI when queueing.
		 */
		public bool $public = true,
	) {}
}
