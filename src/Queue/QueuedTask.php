<?php declare(strict_types=1);

namespace Torr\TaskManager\Queue;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Messenger\Envelope;

/**
 * A task that is waiting in a queue.
 *
 * @api
 */
#[Exclude]
final readonly class QueuedTask
{
	/**
	 */
	public function __construct (
		/**
		 * The stable id of this queued task.
		 * Is null if the transport doesn't provide message ids, so the task can't be removed individually.
		 */
		public ?QueuedTaskId $id,
		public string $queue,
		public Envelope $envelope,
		/**
		 * The task id (see `TaskIdStamp`), which links to the `TaskLog`.
		 * Is null for messages that were queued without the task manager integration.
		 */
		public ?string $taskId,
		public string $label,
		public ?string $group,
		/**
		 * The number of retries of this task (0 for the first run)
		 */
		public int $retryCount,
		/**
		 * Whether a worker already started to handle this task.
		 *
		 * Only tasks extending `Task` can be detected as started, as only those have a task run while they are handled.
		 */
		public bool $isStarted,
	) {}

	/**
	 * Whether this task can be removed from the queue
	 */
	public function isRemovable () : bool
	{
		return null !== $this->id && !$this->isStarted;
	}
}
