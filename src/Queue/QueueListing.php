<?php declare(strict_types=1);

namespace Torr\TaskManager\Queue;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The (possibly partial) list of tasks waiting in a queue.
 *
 * @api
 */
#[Exclude]
final readonly class QueueListing
{
	/**
	 */
	public function __construct (
		public string $queue,
		public bool $isFailureQueue,
		/**
		 * Whether the transport supports listing (and removing) single tasks.
		 * If not, only the total count might be available.
		 */
		public bool $supportsListing,
		/**
		 * The total number of tasks in the queue, or null if the transport can't count them.
		 */
		public ?int $totalCount,
		/**
		 * The tasks in the queue (up to the requested limit), in the order the transport returns them.
		 *
		 * @var list<QueuedTask>
		 */
		public array $tasks,
		/**
		 * Whether there are more tasks in the queue than listed
		 */
		public bool $hasMore,
	) {}
}
